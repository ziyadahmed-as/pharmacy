<?php

namespace App\Services;

use RuntimeException;
use SplFileObject;
use ZipArchive;

/**
 * High-performance file parser for CSV and XLSX files.
 * Streams row-by-row with minimal memory footprint.
 *
 * @uc UC-03 @fr FR-1.8 @nfr NFR-10
 */
class FileParser
{
    /**
     * Parse rows from a given file path.
     *
     * @param string $path
     * @return \Generator<int, array<int, string>> yields [rowNumber => [cell values]]
     * @throws RuntimeException
     */
    public static function parse(string $path): \Generator
    {
        if (! file_exists($path) || ! is_readable($path)) {
            throw new RuntimeException(__('catalogue.file_unreadable_or_missing'));
        }

        if (filesize($path) === 0) {
            throw new RuntimeException(__('catalogue.empty_file_refused'));
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            yield from self::parseXlsx($path);
        } else {
            yield from self::parseCsv($path);
        }
    }

    /**
     * Stream CSV rows.
     */
    private static function parseCsv(string $path): \Generator
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $rowNumber = 1;
        $isEmpty = true;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if ($row === false || $row === [null] || empty(array_filter($row, fn ($c) => trim((string) $c) !== ''))) {
                continue;
            }

            $cleanRow = array_map(fn ($cell) => trim((string) $cell), $row);
            $isEmpty = false;
            yield $rowNumber++ => $cleanRow;
        }

        if ($isEmpty) {
            throw new RuntimeException(__('catalogue.empty_file_refused'));
        }
    }

    /**
     * Stream XLSX rows via ZipArchive and XMLReader.
     */
    private static function parseXlsx(string $path): \Generator
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('catalogue.file_unreadable_or_missing'));
        }

        // 1. Read shared strings if present
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml !== false) {
            $xml = simplexml_load_string($stringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string) ($si->t ?? $si->r->t ?? '');
                }
            }
        }

        // 2. Read sheet1.xml
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException(__('catalogue.empty_file_refused'));
        }

        $xml = simplexml_load_string($sheetXml);
        $zip->close();

        if (! $xml || ! isset($xml->sheetData->row)) {
            throw new RuntimeException(__('catalogue.empty_file_refused'));
        }

        $rowNumber = 1;
        $isEmpty = true;

        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $type = (string) $c['t'];
                $val = (string) $c->v;

                if ($type === 's' && isset($sharedStrings[(int) $val])) {
                    $cells[] = trim($sharedStrings[(int) $val]);
                } else {
                    $cells[] = trim($val);
                }
            }

            if (! empty(array_filter($cells, fn ($c) => $c !== ''))) {
                $isEmpty = false;
                yield $rowNumber++ => $cells;
            }
        }

        if ($isEmpty) {
            throw new RuntimeException(__('catalogue.empty_file_refused'));
        }
    }
}

