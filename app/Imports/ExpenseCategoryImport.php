<?php

namespace App\Imports;

use App\Models\ExpenseCategory;

class ExpenseCategoryImport extends BaseImport
{
    protected array $importedRows = [];

    protected function model(array $row): void
    {
        if (empty(array_filter($row)) || empty($row['name'] ?? null)) {
            return;
        }

        $code = $row['code'] ?? null;

        ExpenseCategory::updateOrCreate(
            ['code' => $code],
            [
                'name'        => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active'   => isset($row['is_active']) ? (int) $row['is_active'] : 1,
            ]
        );

        $this->importedRows[] = $row;
    }

    public function result(): array
    {
        return [
            'success' => count($this->importedRows),
            'errors'  => $this->errors ?? [],
        ];
    }
}
