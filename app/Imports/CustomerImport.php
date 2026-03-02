<?php

namespace App\Imports;

use App\Models\Customer;

class CustomerImport extends BaseImport
{
    protected array $importedRows = [];

    /**
     * Get value from row by key (try exact, then lowercase keys for Excel header variants).
     */
    private function getRowValue(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && (string) $row[$key] !== '') {
                return $row[$key];
            }
        }
        $lower = array_change_key_case($row, CASE_LOWER);
        foreach ($keys as $key) {
            $k = strtolower($key);
            if (array_key_exists($k, $lower) && (string) $lower[$k] !== '') {
                return $lower[$k];
            }
        }
        return null;
    }

    protected function model(array $row): void
    {
        $name = $this->getRowValue($row, 'name', 'customer_name', 'full_name', 'customer');
        if ($name === null || trim((string) $name) === '') {
            // Fallback: first column as name (when file has no header or numeric keys)
            $name = $row[0] ?? $row['0'] ?? null;
        }
        if (empty($name) || trim((string) $name) === '') {
            return;
        }

        $phone = $this->getRowValue($row, 'phone', 'mobile', 'contact', 'contact_number') ?? $row[2] ?? $row['2'] ?? null;
        $email = $this->getRowValue($row, 'email', 'email_address') ?? $row[1] ?? $row['1'] ?? null;

        $data = [
            'name'        => trim((string) $name),
            'email'       => $email !== null ? trim((string) $email) : null,
            'phone'       => $phone !== null ? trim((string) $phone) : null,
            'gst_number'  => $this->getRowValue($row, 'gst_number', 'gst', 'gstin'),
            'address'     => $this->getRowValue($row, 'address', 'address_line'),
            'city'        => $this->getRowValue($row, 'city'),
            'state'       => $this->getRowValue($row, 'state'),
            'postal_code' => $this->getRowValue($row, 'postal_code', 'pincode', 'pin', 'zip'),
            'country'     => $this->getRowValue($row, 'country'),
            'credit_limit'=> (float) ($this->getRowValue($row, 'credit_limit') ?? 0),
            'balance'     => (float) ($this->getRowValue($row, 'balance') ?? 0),
            'is_active'   => true,
        ];

        $val = $this->getRowValue($row, 'is_active');
        if ($val !== null && $val !== '') {
            $data['is_active'] = (bool) (is_numeric($val) ? (int) $val : $val);
        }

        $gst = $data['gst_number'] ?? null;
        if ($gst !== null && trim((string) $gst) !== '') {
            Customer::updateOrCreate(['gst_number' => trim((string) $gst)], $data);
        } elseif (! empty($data['phone'])) {
            Customer::updateOrCreate(['phone' => $data['phone']], $data);
        } elseif (! empty($data['email'])) {
            Customer::updateOrCreate(['email' => $data['email']], $data);
        } else {
            Customer::create($data);
        }

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
