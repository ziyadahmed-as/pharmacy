<?php

namespace App\Services;

use App\Models\DocumentSequence as DocumentSequenceModel;
use Illuminate\Support\Facades\DB;

class DocumentSequence
{
    /**
     * Atomically obtain the next sequence string with row locking.
     */
    public static function next(string $type, string $prefix = ''): string
    {
        return DB::transaction(function () use ($type, $prefix) {
            $seq = DocumentSequenceModel::where('type', $type)->lockForUpdate()->first();

            if (! $seq) {
                throw new \RuntimeException("Document sequence for '{$type}' does not exist. Sequences must be pre-configured.");
            }

            $currentVal = $seq->next_value;
            $seq->next_value = $currentVal + 1;
            $seq->save();

            $p = $prefix !== '' ? $prefix : ($seq->prefix ?: strtoupper(substr($type, 0, 3)));
            $year = now()->format('Y');

            return sprintf('%s-%s-%05d', $p, $year, $currentVal);
        });
    }
}
