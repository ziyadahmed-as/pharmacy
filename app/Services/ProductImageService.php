<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles product image upload, validation, storage, and thumbnail generation.
 *
 * Rules:
 *  - Allowed MIME types (checked via finfo on the binary content, not the file extension): image/jpeg, image/png, image/webp
 *  - Maximum file size: 2 MB
 *  - Originals stored on the 'private' disk (storage/app/private/product-images/) — not web-accessible
 *  - 200×200 thumbnails stored on the 'public' disk  (storage/app/public/product-thumbs/) — served via /storage/product-thumbs/
 *  - Filenames are random (UUID-based) so they cannot be guessed or enumerated
 *  - Thumbnail generation uses the GD extension; no third-party package required
 *
 * @fr FR-1.1 (product image)
 */
final class ProductImageService
{
    /** Allowed MIME types — checked on the binary, not the filename extension. */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /** Maximum upload size in bytes (2 MB). */
    private const MAX_BYTES = 2 * 1024 * 1024;

    /** Thumbnail dimensions in pixels. */
    private const THUMB_SIZE = 200;

    /** Storage subdirectories. */
    private const ORIG_DIR  = 'product-images';
    private const THUMB_DIR = 'product-thumbs';

    /**
     * Validate, store, and thumbnail an uploaded product image.
     *
     * Returns an array with two keys:
     *   'image_path'     — path on the 'private' disk  (store in products.image_path)
     *   'thumbnail_path' — path on the 'public' disk   (use to build the public URL)
     *
     * @throws ValidationException  When the file is too large, wrong type, or GD fails
     *
     * @return array{image_path: string, thumbnail_path: string}
     */
    public function store(UploadedFile $file): array
    {
        $this->validateFile($file);

        $stem      = Str::uuid()->toString();
        $extension = $this->safeExtension($file->path());

        $origPath  = self::ORIG_DIR  . '/' . $stem . '.' . $extension;
        $thumbPath = self::THUMB_DIR . '/' . $stem . '_thumb.jpg';   // thumbnails always JPEG

        // Store the original on the private (non-web-accessible) disk.
        Storage::disk('private')->put($origPath, file_get_contents($file->path()));

        // Generate and store the thumbnail on the public disk.
        $thumbJpeg = $this->makeThumbnail($file->path());
        Storage::disk('public')->put($thumbPath, $thumbJpeg);

        return [
            'image_path'     => $origPath,
            'thumbnail_path' => $thumbPath,
        ];
    }

    /**
     * Delete the original and thumbnail for a product, silently ignoring
     * missing files (safe to call even when only one file exists).
     */
    public function delete(?string $imagePath, ?string $thumbnailPath): void
    {
        if ($imagePath) {
            Storage::disk('private')->delete($imagePath);
        }
        if ($thumbnailPath) {
            Storage::disk('public')->delete($thumbnailPath);
        }
    }

    /**
     * Return the public URL for a thumbnail, or the placeholder URL when none exists.
     */
    public static function thumbnailUrl(?string $thumbnailPath): string
    {
        if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
            return Storage::disk('public')->url($thumbnailPath);
        }

        return asset('images/product-placeholder.png');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws ValidationException
     */
    private function validateFile(UploadedFile $file): void
    {
        // Size check first (cheap).
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => __('catalogue.image_too_large', ['max' => '2 MB']),
            ]);
        }

        // MIME check on the binary content — not on the filename extension.
        $detectedMime = $this->detectMime($file->path());

        if (! in_array($detectedMime, self::ALLOWED_MIME, true)) {
            throw ValidationException::withMessages([
                'image' => __('catalogue.image_invalid_type', [
                    'allowed' => implode(', ', ['JPG', 'PNG', 'WEBP']),
                    'detected' => $detectedMime,
                ]),
            ]);
        }
    }

    /**
     * Detect MIME type by reading the file's binary signature (magic bytes).
     * Uses PHP's finfo extension, which is bundled with PHP 5.3+.
     */
    private function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: 'application/octet-stream';
    }

    /**
     * Map detected MIME to a safe file extension (never trusts the upload name).
     */
    private function safeExtension(string $path): string
    {
        return match ($this->detectMime($path)) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',    // image/jpeg — the validated MIME guarantees this
        };
    }

    /**
     * Create a 200×200 centre-crop thumbnail as a JPEG binary string using GD.
     *
     * Strategy:
     *  1. Load the source image with the appropriate GD loader.
     *  2. Calculate the largest centered square crop that fits the original.
     *  3. Resample to 200×200.
     *  4. Encode as JPEG (quality 85) and return the raw bytes.
     *
     * @throws ValidationException  When GD cannot parse the image
     */
    private function makeThumbnail(string $path): string
    {
        $mime = $this->detectMime($path);

        $src = match ($mime) {
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => @imagecreatefromjpeg($path),
        };

        if ($src === false) {
            throw ValidationException::withMessages([
                'image' => __('catalogue.image_processing_failed'),
            ]);
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // Centre-crop to the largest square that fits.
        $cropSize = min($origW, $origH);
        $cropX    = (int) (($origW - $cropSize) / 2);
        $cropY    = (int) (($origH - $cropSize) / 2);

        $thumb = imagecreatetruecolor(self::THUMB_SIZE, self::THUMB_SIZE);
        if ($thumb === false) {
            imagedestroy($src);
            throw ValidationException::withMessages([
                'image' => __('catalogue.image_processing_failed'),
            ]);
        }

        // Preserve transparency for PNG sources.
        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($thumb, 0, 0, self::THUMB_SIZE, self::THUMB_SIZE, $transparent);
            }
        }

        imagecopyresampled(
            $thumb, $src,
            0, 0,
            $cropX, $cropY,
            self::THUMB_SIZE, self::THUMB_SIZE,
            $cropSize, $cropSize
        );

        imagedestroy($src);

        // Capture JPEG output into a string buffer.
        ob_start();
        imagejpeg($thumb, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($thumb);

        if ($jpeg === false || $jpeg === '') {
            throw ValidationException::withMessages([
                'image' => __('catalogue.image_processing_failed'),
            ]);
        }

        return $jpeg;
    }
}
