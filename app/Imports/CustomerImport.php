<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class ProductImport extends BaseImport
{
    protected array $importedRows = [];

    protected function model(array $row): void
    {
        // Skip completely empty rows or missing required 'name' or 'sku'
        if (empty(array_filter($row)) || empty($row['name']) || empty($row['sku'])) {
            return; // skip invalid row
        }

        // Get related category and brand IDs
        $categoryId = $this->getCategoryId($row['category_code'] ?? null);
        $brandId    = $this->getBrandId($row['brand_code'] ?? null);

        // Insert or update product based on SKU
        Product::updateOrCreate(
            ['sku' => $row['sku']],
            [
                'name'            => $row['name'],
                'barcode'         => $row['barcode'] ?? null,
                'category_id'     => $categoryId,
                'brand_id'        => $brandId,
                'description'     => $row['description'] ?? null,
                'cost_price'      => $row['cost_price'] ?? 0,
                'selling_price'   => $row['selling_price'] ?? 0,
                'stock_quantity'  => $row['stock_quantity'] ?? 0,
                'min_stock_level' => $row['min_stock_level'] ?? 0,
                'unit'            => $row['unit'] ?? 'pcs',
                'is_active'       => $row['is_active'] ?? 1,
            ]
        );

        // Track successfully imported row
        $this->importedRows[] = $row;
    }

    // Helper to get category ID by code
    private function getCategoryId(?string $code): ?int
    {
        if (!$code) return null;
        return Category::where('code', $code)->value('id');
    }

    // Helper to get brand ID by code
    private function getBrandId(?string $code): ?int
    {
        if (!$code) return null;
        return Brand::where('code', $code)->value('id');
    }

    // Return import result
    public function result(): array
    {
        return [
            'success' => count($this->importedRows),
            'errors'  => $this->errors ?? [],
        ];
    }
}
