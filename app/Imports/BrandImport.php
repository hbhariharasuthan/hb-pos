<?php

namespace App\Imports;

use App\Models\Brand;

class BrandImport extends BaseImport
{
    protected array $importedRows = [];

    protected function model(array $row): void
    {
        // Skip empty rows or rows missing name
        if (empty(array_filter($row)) || empty($row['name'])) {
            return; // just skip
        }

        Brand::updateOrCreate(
            ['code' => $row['code'] ?? null],
            [
                'name'        => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active'   => $row['is_active'] ?? 1,
            ]
        );

        // Track successfully imported row
        $this->importedRows[] = $row;
    }

    // Override result method to return only valid rows
    public function result(): array
    {
        return [
            'success' => count($this->importedRows),
            'errors'  => $this->errors ?? [],
        ];
    }
}
