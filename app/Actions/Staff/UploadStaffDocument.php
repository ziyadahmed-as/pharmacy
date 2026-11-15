<?php

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Upload a confidential staff document with private storage and strict file limits.
 *
 * @uc UC-39
 *
 * @fr FR-10.3
 */
class UploadStaffDocument
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    public const MAX_SIZE_KB = 5120; // 5 MB

    /**
     * @param array{
     *     type: string,
     *     file: UploadedFile,
     *     issued_at?: string|null,
     *     expires_at?: string|null
     * } $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, Staff $staff, array $data): StaffDocument
    {
        // SA is forbidden from accessing/managing staff business documents
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasAnyRole([Role::PharmacyManager, Role::Owner])) {
            throw new AuthorizationException(__('staff.documents.unauthorized'));
        }

        $type = $data['type'] ?? '';
        if (! in_array($type, StaffDocument::TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => __('staff.documents.invalid_type'),
            ]);
        }

        /** @var UploadedFile|null $file */
        $file = $data['file'] ?? null;
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => __('staff.documents.file_required'),
            ]);
        }

        // Validate file size (max 5 MB)
        if ($file->getSize() > (self::MAX_SIZE_KB * 1024)) {
            throw ValidationException::withMessages([
                'file' => __('staff.documents.file_too_large', ['max' => self::MAX_SIZE_KB / 1024]),
            ]);
        }

        // Validate extension
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => __('staff.documents.invalid_extension', ['allowed' => implode(', ', self::ALLOWED_EXTENSIONS)]),
            ]);
        }

        $issuedAt = ! empty($data['issued_at']) ? $data['issued_at'] : null;
        $expiresAt = ! empty($data['expires_at']) ? $data['expires_at'] : null;

        if ($issuedAt && $expiresAt && $expiresAt < $issuedAt) {
            throw ValidationException::withMessages([
                'expires_at' => __('staff.documents.expires_after_issued'),
            ]);
        }

        // Store privately on local disk (never public)
        $directory = 'staff-documents/'.$staff->id;
        $path = $file->store($directory, 'local');

        if (! $path) {
            throw new \RuntimeException('Failed to store document file to private storage.');
        }

        $doc = StaffDocument::create([
            'staff_id' => $staff->id,
            'type' => $type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'uploaded_by' => $user->id,
        ]);

        AuditLogger::record(
            action: 'staff.document.uploaded',
            subject: $doc,
            new: [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'type' => $type,
                'file_name' => $doc->file_name,
                'file_size' => $doc->file_size,
                'expires_at' => $expiresAt,
            ],
            user: $user,
            description: "Uploaded {$type} document for {$staff->name}"
        );

        return $doc;
    }
}
