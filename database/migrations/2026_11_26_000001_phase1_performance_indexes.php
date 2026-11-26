<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DATE_TIME_TABLES = [
        'inbounds',
        'material_outs',
        'material_returns',
        'productions',
        'deliveries',
        'return_to_suppliers',
        'production_ngs',
        'stock_adjustments',
        'problem_delays',
    ];

    public function up(): void
    {
        foreach (self::DATE_TIME_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index('date_time');
            });
        }

        Schema::table('lot_tracers', function (Blueprint $table) {
            $table->index('status_lot');
        });

        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->index('status_stock');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropIndex(['status_stock']);
        });

        Schema::table('lot_tracers', function (Blueprint $table) {
            $table->dropIndex(['status_lot']);
        });

        foreach (self::DATE_TIME_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['date_time']);
            });
        }
    }
};