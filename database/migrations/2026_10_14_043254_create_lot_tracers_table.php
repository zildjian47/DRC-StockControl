<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_tracers', function (Blueprint $table) {
            $table->id();
            $table->string('lot_number')->index();
            $table->foreignId('process_id')->constrained('processes')->onDelete('restrict');
            $table->foreignId('material_item_id')->constrained('items')->onDelete('restrict');
            $table->foreignId('target_item_id')->constrained('items')->onDelete('restrict');
            $table->decimal('qty_material_out', 10, 2)->default(0);
            $table->decimal('qty_material_return', 10, 2)->default(0);
            $table->decimal('target_qty_estimasi', 10, 2)->default(0);
            $table->decimal('actual_qty_prod', 10, 2)->default(0);
            $table->decimal('qty_ng', 10, 2)->default(0);
            $table->decimal('qty_wip', 10, 2)->default(0);
            $table->string('status_lot')->default('ON PROGRESS');
            $table->timestamps();

            $table->unique(['lot_number', 'target_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_tracers');
    }
};