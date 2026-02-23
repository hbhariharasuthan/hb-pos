<?php

namespace App\Imports;

use App\Models\Brand;

class BrandImport extends BaseImport
{
    protected function model(array $row): void
    {
        if (empty($row['name'])) {
            throw new \Exception('Brand name is required');
        }

        Brand::updateOrCreate(
            ['code' => $row['code'] ?? null],
            [
                'name'        => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active'   => $row['is_active'] ?? 1,
            ]
        );
    }
}

