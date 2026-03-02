<?php

namespace App\Imports;

use App\Models\GstSlab;

class GstSlabImport extends BaseImport
{
    protected array $importedRows = [];

    protected function model(array $row): void
    {
        $hsn = $row['hsn_code'] ?? $row['hsn'] ?? $row[0] ?? null;
        if (empty($hsn) || trim((string) $hsn) === '') {
            return;
        }

        $gstPercent = $row['gst_percent'] ?? $row['gst'] ?? $row[1] ?? 0;
        $description = $row['description'] ?? $row['desc'] ?? $row[2] ?? null;

        GstSlab::updateOrCreate(
            ['hsn_code' => trim((string) $hsn)],
            [
                'gst_percent' => (float) $gstPercent,
                'description' => $description !== null && trim((string) $description) !== '' ? trim((string) $description) : null,
            ]
        );

        $this->importedRows[] = $row;
    }

    public function result(): array
    {
        return [
            'success' => count($this->importedRows),
            'errors'   => $this->errors ?? [],
        ];
    }
}
