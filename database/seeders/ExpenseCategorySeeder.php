<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Rent', 'code' => 'RENT', 'description' => 'Office or shop rent'],
            ['name' => 'Utilities', 'code' => 'UTIL', 'description' => 'Electricity, water, etc.'],
            ['name' => 'Salaries', 'code' => 'SAL', 'description' => 'Staff salaries and wages'],
            ['name' => 'Office Supplies', 'code' => 'OFF', 'description' => 'Stationery and office supplies'],
            ['name' => 'Transport', 'code' => 'TRP', 'description' => 'Travel and conveyance'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Advertising and marketing'],
            ['name' => 'Maintenance', 'code' => 'MNT', 'description' => 'Repairs and maintenance'],
            ['name' => 'Miscellaneous', 'code' => 'MISC', 'description' => 'Other expenses'],
        ];

        foreach ($categories as $row) {
            ExpenseCategory::firstOrCreate(
                ['code' => $row['code']],
                array_merge($row, ['is_active' => true])
            );
        }
    }
}
