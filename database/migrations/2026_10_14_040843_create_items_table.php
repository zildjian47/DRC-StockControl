<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('part_no')->nullable()->index();
            $table->string('part_name')->index();
            $table->string('type'); // enum: 'RM', 'FG', 'FG/KOMPONEN', 'KOMPONEN', 'TRANSIT'
            $table->string('uom'); // ui dropdown list: PCS, KG, SHEET, BATANG, MTR, LTR, SET
            // Relasi ke tabel companies
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('restrict');
            
            $table->decimal('qty_po', 10, 2)->default(0);
            $table->integer('working_days')->default(0);
            $table->decimal('qty_kanban', 10, 2)->default(0);
            $table->decimal('std_day_1_po_wd', 10, 2)->default(0);
            $table->decimal('std_day_1', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};