<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Electrical product categories
        $categories = [
            'Electronics',
            'Home Appliances',
            'Computer & Accessories',
            'Mobile & Accessories',
            'Audio & Video',
            'Gaming',
            'Smart Home',
            'Power & Batteries',
            'Cables & Connectors',
            'Lighting'
        ];

        // Ensure categories exist
        $categoryIds = [];
        foreach ($categories as $catName) {
            $category = Category::firstOrCreate(
                ['name' => $catName],
                [
                    'code' => strtoupper(substr($catName, 0, 4)),
                    'description' => "Category for {$catName}",
                    'is_active' => true
                ]
            );
            $categoryIds[] = $category->id;
        }

        // Electrical product templates
        $productTemplates = [
            // Electronics
            ['name' => 'LED TV', 'category' => 'Electronics', 'cost' => [300, 800], 'price' => [400, 1200], 'unit' => 'unit'],
            ['name' => 'Smart TV', 'category' => 'Electronics', 'cost' => [500, 1500], 'price' => [700, 2000], 'unit' => 'unit'],
            ['name' => '4K Monitor', 'category' => 'Electronics', 'cost' => [200, 600], 'price' => [300, 900], 'unit' => 'unit'],
            ['name' => 'HDMI Cable', 'category' => 'Cables & Connectors', 'cost' => [5, 25], 'price' => [10, 40], 'unit' => 'pcs'],
            ['name' => 'USB Cable', 'category' => 'Cables & Connectors', 'cost' => [3, 15], 'price' => [5, 25], 'unit' => 'pcs'],
            
            // Home Appliances
            ['name' => 'Microwave Oven', 'category' => 'Home Appliances', 'cost' => [80, 300], 'price' => [120, 450], 'unit' => 'unit'],
            ['name' => 'Refrigerator', 'category' => 'Home Appliances', 'cost' => [400, 1200], 'price' => [600, 1800], 'unit' => 'unit'],
            ['name' => 'Washing Machine', 'category' => 'Home Appliances', 'cost' => [300, 800], 'price' => [450, 1200], 'unit' => 'unit'],
            ['name' => 'Air Conditioner', 'category' => 'Home Appliances', 'cost' => [500, 2000], 'price' => [750, 3000], 'unit' => 'unit'],
            ['name' => 'Electric Kettle', 'category' => 'Home Appliances', 'cost' => [20, 80], 'price' => [30, 120], 'unit' => 'unit'],
            ['name' => 'Coffee Maker', 'category' => 'Home Appliances', 'cost' => [50, 200], 'price' => [75, 300], 'unit' => 'unit'],
            ['name' => 'Blender', 'category' => 'Home Appliances', 'cost' => [30, 150], 'price' => [45, 225], 'unit' => 'unit'],
            ['name' => 'Toaster', 'category' => 'Home Appliances', 'cost' => [25, 100], 'price' => [35, 150], 'unit' => 'unit'],
            
            // Computer & Accessories
            ['name' => 'Laptop', 'category' => 'Computer & Accessories', 'cost' => [500, 2000], 'price' => [750, 3000], 'unit' => 'unit'],
            ['name' => 'Desktop Computer', 'category' => 'Computer & Accessories', 'cost' => [600, 2500], 'price' => [900, 3750], 'unit' => 'unit'],
            ['name' => 'Keyboard', 'category' => 'Computer & Accessories', 'cost' => [20, 150], 'price' => [30, 225], 'unit' => 'unit'],
            ['name' => 'Mouse', 'category' => 'Computer & Accessories', 'cost' => [10, 80], 'price' => [15, 120], 'unit' => 'unit'],
            ['name' => 'Webcam', 'category' => 'Computer & Accessories', 'cost' => [30, 200], 'price' => [45, 300], 'unit' => 'unit'],
            ['name' => 'External Hard Drive', 'category' => 'Computer & Accessories', 'cost' => [50, 300], 'price' => [75, 450], 'unit' => 'unit'],
            ['name' => 'USB Flash Drive', 'category' => 'Computer & Accessories', 'cost' => [5, 50], 'price' => [8, 75], 'unit' => 'unit'],
            ['name' => 'Laptop Stand', 'category' => 'Computer & Accessories', 'cost' => [15, 80], 'price' => [25, 120], 'unit' => 'unit'],
            ['name' => 'Monitor Stand', 'category' => 'Computer & Accessories', 'cost' => [20, 100], 'price' => [30, 150], 'unit' => 'unit'],
            
            // Mobile & Accessories
            ['name' => 'Smartphone', 'category' => 'Mobile & Accessories', 'cost' => [200, 1000], 'price' => [300, 1500], 'unit' => 'unit'],
            ['name' => 'Tablet', 'category' => 'Mobile & Accessories', 'cost' => [150, 800], 'price' => [225, 1200], 'unit' => 'unit'],
            ['name' => 'Phone Case', 'category' => 'Mobile & Accessories', 'cost' => [5, 30], 'price' => [10, 45], 'unit' => 'pcs'],
            ['name' => 'Screen Protector', 'category' => 'Mobile & Accessories', 'cost' => [3, 20], 'price' => [5, 30], 'unit' => 'pcs'],
            ['name' => 'Power Bank', 'category' => 'Mobile & Accessories', 'cost' => [15, 80], 'price' => [25, 120], 'unit' => 'unit'],
            ['name' => 'Wireless Charger', 'category' => 'Mobile & Accessories', 'cost' => [20, 100], 'price' => [30, 150], 'unit' => 'unit'],
            ['name' => 'Phone Mount', 'category' => 'Mobile & Accessories', 'cost' => [10, 50], 'price' => [15, 75], 'unit' => 'unit'],
            
            // Audio & Video
            ['name' => 'Wireless Headphones', 'category' => 'Audio & Video', 'cost' => [50, 300], 'price' => [75, 450], 'unit' => 'unit'],
            ['name' => 'Wired Headphones', 'category' => 'Audio & Video', 'cost' => [20, 150], 'price' => [30, 225], 'unit' => 'unit'],
            ['name' => 'Bluetooth Speaker', 'category' => 'Audio & Video', 'cost' => [30, 200], 'price' => [45, 300], 'unit' => 'unit'],
            ['name' => 'Soundbar', 'category' => 'Audio & Video', 'cost' => [100, 500], 'price' => [150, 750], 'unit' => 'unit'],
            ['name' => 'Microphone', 'category' => 'Audio & Video', 'cost' => [40, 250], 'price' => [60, 375], 'unit' => 'unit'],
            ['name' => 'Earbuds', 'category' => 'Audio & Video', 'cost' => [15, 200], 'price' => [25, 300], 'unit' => 'unit'],
            
            // Gaming
            ['name' => 'Gaming Console', 'category' => 'Gaming', 'cost' => [300, 600], 'price' => [450, 900], 'unit' => 'unit'],
            ['name' => 'Gaming Controller', 'category' => 'Gaming', 'cost' => [30, 100], 'price' => [45, 150], 'unit' => 'unit'],
            ['name' => 'Gaming Mouse', 'category' => 'Gaming', 'cost' => [25, 150], 'price' => [40, 225], 'unit' => 'unit'],
            ['name' => 'Gaming Keyboard', 'category' => 'Gaming', 'cost' => [50, 250], 'price' => [75, 375], 'unit' => 'unit'],
            ['name' => 'Gaming Headset', 'category' => 'Gaming', 'cost' => [40, 200], 'price' => [60, 300], 'unit' => 'unit'],
            
            // Smart Home
            ['name' => 'Smart Light Bulb', 'category' => 'Smart Home', 'cost' => [10, 50], 'price' => [15, 75], 'unit' => 'pcs'],
            ['name' => 'Smart Plug', 'category' => 'Smart Home', 'cost' => [15, 60], 'price' => [25, 90], 'unit' => 'unit'],
            ['name' => 'Smart Thermostat', 'category' => 'Smart Home', 'cost' => [100, 300], 'price' => [150, 450], 'unit' => 'unit'],
            ['name' => 'Smart Doorbell', 'category' => 'Smart Home', 'cost' => [80, 250], 'price' => [120, 375], 'unit' => 'unit'],
            ['name' => 'Smart Lock', 'category' => 'Smart Home', 'cost' => [150, 400], 'price' => [225, 600], 'unit' => 'unit'],
            ['name' => 'Smart Camera', 'category' => 'Smart Home', 'cost' => [50, 200], 'price' => [75, 300], 'unit' => 'unit'],
            
            // Power & Batteries
            ['name' => 'AA Batteries', 'category' => 'Power & Batteries', 'cost' => [2, 8], 'price' => [3, 12], 'unit' => 'pack'],
            ['name' => 'AAA Batteries', 'category' => 'Power & Batteries', 'cost' => [2, 8], 'price' => [3, 12], 'unit' => 'pack'],
            ['name' => 'Rechargeable Battery', 'category' => 'Power & Batteries', 'cost' => [5, 30], 'price' => [8, 45], 'unit' => 'unit'],
            ['name' => 'Battery Charger', 'category' => 'Power & Batteries', 'cost' => [10, 50], 'price' => [15, 75], 'unit' => 'unit'],
            ['name' => 'UPS Power Supply', 'category' => 'Power & Batteries', 'cost' => [80, 400], 'price' => [120, 600], 'unit' => 'unit'],
            ['name' => 'Extension Cord', 'category' => 'Power & Batteries', 'cost' => [8, 40], 'price' => [12, 60], 'unit' => 'unit'],
            ['name' => 'Power Strip', 'category' => 'Power & Batteries', 'cost' => [10, 60], 'price' => [15, 90], 'unit' => 'unit'],
            
            // Lighting
            ['name' => 'LED Strip Lights', 'category' => 'Lighting', 'cost' => [15, 80], 'price' => [25, 120], 'unit' => 'meter'],
            ['name' => 'Desk Lamp', 'category' => 'Lighting', 'cost' => [20, 100], 'price' => [30, 150], 'unit' => 'unit'],
            ['name' => 'LED Bulb', 'category' => 'Lighting', 'cost' => [5, 25], 'price' => [8, 40], 'unit' => 'pcs'],
            ['name' => 'Table Lamp', 'category' => 'Lighting', 'cost' => [25, 120], 'price' => [40, 180], 'unit' => 'unit'],
            ['name' => 'Floor Lamp', 'category' => 'Lighting', 'cost' => [40, 200], 'price' => [60, 300], 'unit' => 'unit'],
        ];

        // Get current product count
        $currentCount = Product::count();
        $targetCount = 500;
        $productsCreated = 0;
        $maxAttempts = $targetCount * 2; // Allow some retries
        $attempts = 0;

        while ($productsCreated < ($targetCount - $currentCount) && $attempts < $maxAttempts) {
            $attempts++;
            foreach ($productTemplates as $template) {
                if ($productsCreated >= $targetCount) {
                    break;
                }

                // Find category ID
                $category = Category::where('name', $template['category'])->first();
                $categoryId = $category ? $category->id : $categoryIds[array_rand($categoryIds)];

                // Generate variations
                $variations = [
                    ['size' => 'Small', 'color' => 'Black'],
                    ['size' => 'Medium', 'color' => 'White'],
                    ['size' => 'Large', 'color' => 'Silver'],
                    ['size' => 'Small', 'color' => 'Blue'],
                    ['size' => 'Medium', 'color' => 'Red'],
                    ['size' => 'Large', 'color' => 'Gray'],
                    ['size' => 'Standard', 'color' => 'Black'],
                    ['size' => 'Standard', 'color' => 'White'],
                ];

                $variation = $variations[array_rand($variations)];
                $productName = "{$template['name']} - {$variation['size']} {$variation['color']}";

                // Generate unique SKU
                $skuPrefix = strtoupper(substr(str_replace(' ', '', $template['name']), 0, 6));
                $baseSku = $skuPrefix . '-' . strtoupper(substr($variation['size'], 0, 2)) . '-' . strtoupper(substr($variation['color'], 0, 2));
                $skuCounter = 1;
                do {
                    $sku = $baseSku . '-' . str_pad($skuCounter, 4, '0', STR_PAD_LEFT);
                    $skuCounter++;
                } while (Product::where('sku', $sku)->exists() && $skuCounter < 10000);

                // Generate unique barcode
                $barcodeCounter = 1;
                do {
                    $barcode = 'ELEC' . str_pad($barcodeCounter, 10, '0', STR_PAD_LEFT);
                    $barcodeCounter++;
                } while (Product::where('barcode', $barcode)->exists() && $barcodeCounter < 100000);

                // Generate prices
                $costPrice = rand($template['cost'][0] * 100, $template['cost'][1] * 100) / 100;
                $sellingPrice = rand($template['price'][0] * 100, $template['price'][1] * 100) / 100;
                
                // Ensure selling price is higher than cost
                if ($sellingPrice <= $costPrice) {
                    $sellingPrice = $costPrice * (1 + (rand(20, 50) / 100));
                }

                // Generate stock
                $stockQuantity = rand(0, 200);
                $minStockLevel = rand(5, 30);

                // Use firstOrCreate to avoid duplicates
                Product::firstOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $productName,
                        'barcode' => $barcode,
                        'category_id' => $categoryId,
                        'description' => "High-quality {$productName}. Perfect for modern electrical needs.",
                        'cost_price' => round($costPrice, 2),
                        'selling_price' => round($sellingPrice, 2),
                        'stock_quantity' => $stockQuantity,
                        'min_stock_level' => $minStockLevel,
                        'unit' => $template['unit'],
                        'is_active' => true,
                    ]
                );

                $productsCreated++;
            }
        }

        $finalCount = Product::count();
        $this->command->info("Product seeding completed! Total products in database: {$finalCount}");
        if ($productsCreated > 0) {
            $this->command->info("Created {$productsCreated} new electrical products.");
        } else {
            $this->command->info("No new products created. Database already has {$finalCount} products.");
        }
    }
}
