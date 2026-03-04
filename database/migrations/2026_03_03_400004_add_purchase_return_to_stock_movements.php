<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('purchase', 'sale', 'return', 'purchase_return', 'adjustment', 'transfer') DEFAULT 'adjustment'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('purchase', 'sale', 'return', 'adjustment', 'transfer') DEFAULT 'adjustment'");
    }
};
