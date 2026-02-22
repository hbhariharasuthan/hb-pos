<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        /* ===============================
         | 1. BRANDS
         ===============================*/
        $brands = [
            'Havells','Anchor','Legrand','Philips','Crompton',
            'Finolex','Polycab',
            'Astral','Supreme','Ashirvad','Prince',
            'Sintex',
            'Jaquar','Cera','Parryware','Hindware',
            'Texmo','Kirloskar'
        ];

        $brandIds = [];
        foreach ($brands as $name) {
            $brand = Brand::firstOrCreate(
                ['name' => $name],
                ['code' => strtoupper(substr($name, 0, 4)), 'is_active' => true]
            );
            $brandIds[$name] = $brand->id;
        }

        /* ===============================
         | 2. CATEGORIES
         ===============================*/
        $categories = [
            'Wires & Cables',
            'Switches & Sockets',
            'Lighting',
            'Fans',
            'Pipes & Fittings',
            'Bathroom Sanitary',
            'Water Tanks',
            'Motors & Pumps',
            'Building Materials',
            'Electrical Accessories'
        ];

        $categoryIds = [];
        foreach ($categories as $catName) {
            $category = Category::firstOrCreate(
                ['name' => $catName],
                [
                    'code' => strtoupper(substr(str_replace(' ', '', $catName), 0, 5)),
                    'description' => "{$catName} products",
                    'is_active' => true
                ]
            );
            $categoryIds[$catName] = $category->id;
        }

        /* ===============================
         | 3. PRODUCT TEMPLATES
         ===============================*/
        $productTemplates = [

            // ELECTRICAL
            ['name'=>'PVC Copper Wire','category'=>'Wires & Cables','brand'=>'Finolex','unit'=>'meter','cost'=>[12,18],'price'=>[20,30]],
            ['name'=>'Modular Switch 6A','category'=>'Switches & Sockets','brand'=>'Anchor','unit'=>'pcs','cost'=>[35,70],'price'=>[60,120]],
            ['name'=>'LED Bulb','category'=>'Lighting','brand'=>'Philips','unit'=>'pcs','cost'=>[90,140],'price'=>[150,220]],
            ['name'=>'Ceiling Fan','category'=>'Fans','brand'=>'Crompton','unit'=>'unit','cost'=>[1200,1800],'price'=>[1800,2600]],

            // PLUMBING
            ['name'=>'UPVC Pipe','category'=>'Pipes & Fittings','brand'=>'Astral','unit'=>'meter','cost'=>[45,75],'price'=>[80,130]],
            ['name'=>'CPVC Elbow','category'=>'Pipes & Fittings','brand'=>'Ashirvad','unit'=>'pcs','cost'=>[20,35],'price'=>[40,65]],

            // BATHROOM
            ['name'=>'Wash Basin','category'=>'Bathroom Sanitary','brand'=>'Cera','unit'=>'unit','cost'=>[900,1500],'price'=>[1500,2600]],
            ['name'=>'Health Faucet','category'=>'Bathroom Sanitary','brand'=>'Jaquar','unit'=>'unit','cost'=>[800,1400],'price'=>[1400,2300]],
            ['name'=>'Western Toilet','category'=>'Bathroom Sanitary','brand'=>'Hindware','unit'=>'unit','cost'=>[3500,6000],'price'=>[6000,9500]],

            // WATER TANK
            ['name'=>'Triple Layer Water Tank','category'=>'Water Tanks','brand'=>'Sintex','unit'=>'unit','cost'=>[6000,9000],'price'=>[9000,14000]],

            // MOTORS
            ['name'=>'Water Motor','category'=>'Motors & Pumps','brand'=>'Texmo','unit'=>'unit','cost'=>[6500,9000],'price'=>[9500,13500]],

            // BUILDING
            ['name'=>'CPVC Adhesive','category'=>'Building Materials','brand'=>'Astral','unit'=>'pcs','cost'=>[120,180],'price'=>[220,320]],
        ];

        /* ===============================
         | 4. VARIATIONS
         ===============================*/
        $variants = [
            '0.75 Sqmm','1 Sqmm','1.5 Sqmm','2.5 Sqmm',
            '15mm','20mm','25mm',
            '10L','25L','50L','1000L',
            'Standard','Heavy Duty','Premium'
        ];

        /* ===============================
         | 5. GENERATE PRODUCTS
         ===============================*/
        $target = 500;
        $created = 0;

        while ($created < $target) {
            foreach ($productTemplates as $template) {

                if ($created >= $target) break;

                $variant = $variants[array_rand($variants)];
                $name = "{$template['name']} {$variant}";

                $sku = strtoupper(substr(str_replace(' ', '', $template['name']), 0, 6))
                    . '-' . strtoupper(substr($variant, 0, 4))
                    . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                if (Product::where('sku', $sku)->exists()) continue;

                $cost = rand($template['cost'][0]*100, $template['cost'][1]*100)/100;
                $price = max(
                    rand($template['price'][0]*100, $template['price'][1]*100)/100,
                    $cost * 1.25
                );

                Product::create([
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => 'HD' . str_pad(rand(1, 9999999999), 10, '0', STR_PAD_LEFT),
                    'category_id' => $categoryIds[$template['category']],
                    'brand_id' => $brandIds[$template['brand']],
                    'description' => "{$template['brand']} {$name} – suitable for electrical & plumbing works",
                    'cost_price' => round($cost, 2),
                    'selling_price' => round($price, 2),
                    'stock_quantity' => rand(5, 300),
                    'min_stock_level' => rand(10, 40),
                    'unit' => $template['unit'],
                    'is_active' => true
                ]);

                $created++;
            }
        }

        $this->command->info("✅ {$created} electrical & plumbing products seeded successfully.");
    }
}
