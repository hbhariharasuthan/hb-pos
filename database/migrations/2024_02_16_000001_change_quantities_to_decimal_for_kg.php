<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products MODIFY stock_quantity DECIMAL(12,3) DEFAULT 0');
        DB::statement('ALTER TABLE products MODIFY min_stock_level DECIMAL(12,3) DEFAULT 0');
        DB::statement('ALTER TABLE sale_items MODIFY quantity DECIMAL(12,3) NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY quantity DECIMAL(12,3) NOT NULL');
        DB::statement('ALTER TABLE return_items MODIFY quantity DECIMAL(12,3) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products MODIFY stock_quantity INT DEFAULT 0');
        DB::statement('ALTER TABLE products MODIFY min_stock_level INT DEFAULT 0');
        DB::statement('ALTER TABLE sale_items MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE stock_movements MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE return_items MODIFY quantity INT NOT NULL');
    }
};
