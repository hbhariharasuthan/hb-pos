<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GstSlab;

class GstSlabSeeder extends Seeder
{
    /**
     * Seed GST slabs with HSN codes for electrical & plumbing POS.
     */
    public function run(): void
    {
        $slabs = [
            ['hsn_code' => '8544', 'gst_percent' => 18.00, 'description' => 'Electrical wires & cables'],
            ['hsn_code' => '8536', 'gst_percent' => 18.00, 'description' => 'Switches & MCB'],
            ['hsn_code' => '8539', 'gst_percent' => 12.00, 'description' => 'LED bulbs & lamps'],
            ['hsn_code' => '8414', 'gst_percent' => 18.00, 'description' => 'Fans & ventilation'],
            ['hsn_code' => '3917', 'gst_percent' => 18.00, 'description' => 'Pipes & fittings'],
            ['hsn_code' => '7324', 'gst_percent' => 18.00, 'description' => 'Bathroom sanitaryware'],
            ['hsn_code' => '8413', 'gst_percent' => 18.00, 'description' => 'Motors & pumps'],
            ['hsn_code' => '3506', 'gst_percent' => 18.00, 'description' => 'Adhesives & sealants'],
            ['hsn_code' => '3925', 'gst_percent' => 18.00, 'description' => 'Water tanks & storage'],
            ['hsn_code' => '6810', 'gst_percent' => 18.00, 'description' => 'Building materials'],
        ];

        foreach ($slabs as $slab) {
            GstSlab::firstOrCreate(
                ['hsn_code' => $slab['hsn_code']],
                $slab
            );
        }

        $this->command->info('✅ GST slabs seeded successfully.');
    }
}
