<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->string('refund_method', 50)->nullable()->after('refund_amount');
            $table->string('refund_reference')->nullable()->after('refund_method');
        });

        // Add 'cancelled' to status enum (MySQL)
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE returns MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn(['refund_method', 'refund_reference']);
        });
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE returns MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending'"
        );
    }
};
