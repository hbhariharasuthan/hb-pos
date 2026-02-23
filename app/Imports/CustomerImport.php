<?php

namespace App\Imports;

use App\Models\Customer;

class CustomerImport extends BaseImport
{
    protected array $importedRows = [];

    protected function model(array $row): void
    {
        // Skip completely empty rows or rows missing required 'name'
        if (empty(array_filter($row)) || empty($row['name'])) {
            return; // skip
        }

        // Insert or update customer based on email (assuming email is unique)
        Customer::updateOrCreate(
            ['email' => $row['email'] ?? null],
            [
                'name'         => $row['name'],
                'phone'        => $row['phone'] ?? null,
                'gst_number'   => $row['gst_number'] ?? null,
                'address'      => $row['address'] ?? null,
                'city'         => $row['city'] ?? null,
                'state'        => $row['state'] ?? null,
                'postal_code'  => $row['postal_code'] ?? null,
                'country'      => $row['country'] ?? null,
                'credit_limit' => $row['credit_limit'] ?? 0,
                'balance'      => $row['balance'] ?? 0,
                'is_active'    => $row['is_active'] ?? 1,
            ]
        );

        // Track successfully imported row
        $this->importedRows[] = $row;
    }

    // Return only valid rows
    public function result(): array
    {
        return [
            'success' => count($this->importedRows),
            'errors'  => $this->errors ?? [],
        ];
    }
}
