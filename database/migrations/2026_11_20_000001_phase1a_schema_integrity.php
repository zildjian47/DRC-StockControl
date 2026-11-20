<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Pastikan 1 item hanya memiliki 1 baris saldo di inventory_stocks
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->unique('item_id');
        });

        // 2. Seragamkan stock_adjustments.user_id menjadi nullable + nullOnDelete
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // 3. Lepas unique index companies agar kompatibel dengan SoftDeletes
        //    (validasi keunikan data aktif dijaga ketat di CompanyController pada Paket 1B)
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['company_code', 'type_company']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unique(['company_code', 'type_company']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropUnique(['item_id']);
        });
    }
};