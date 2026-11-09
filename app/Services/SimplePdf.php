<?php

namespace App\Services;

/**
 * Minimal text-table PDF writer (Helvetica, A4 landscape). No external package is
 * needed for the audit export. Latin text only; use CSV for Amharic content.
 */
final class SimplePdf
{
    /** @param  list<list<string>>  $rows  first row is the header */
    public static function table(string $title, array $rows): string
    {
        $pages = [];
        $lines = [];
        $perPage = 38;
        foreach ($rows as $i => $row) {
            $lines[] = ($i === 0 ? 'B|' : 'N|').self::clip(implode('  |  ', $row), 150);
        }
        foreach (array_chunk($lines, $perPage) as $chunk) {
            $pages[] = $chunk;
        }
        $pages = $pages ?: [[]];

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $n = 5;
        $pageObjs = [];
        foreach ($pages as $pi => $chunk) {
            $y = 560;
            $stream = 'BT /F2 11 Tf 30 580 Td ('.self::esc($title.'  -  page '.($pi + 1).' of '.count($pages)).") Tj ET\n";
            foreach ($chunk as $line) {
                [$weight, $text] = explode('|', $line, 2);
                $font = $weight === 'B' ? 'F2' : 'F1';
                $stream .= "BT /{$font} 7 Tf 30 {$y} Td (".self::esc($text).") Tj ET\n";
                $y -= 14;
            }
            $contentId = $n++;
            $pageId = $n++;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
            $kids[] = "{$pageId} 0 R";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private static function clip(string $s, int $max): string
    {
        $s = preg_replace('/[^\x20-\x7E]/', '?', $s);

        return strlen($s) > $max ? substr($s, 0, $max - 1).'~' : $s;
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[^\x20-\x7E]/', '?', $s));
    }
}
