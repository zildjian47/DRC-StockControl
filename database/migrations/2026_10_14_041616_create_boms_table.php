<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->string('bom_group')->nullable()->index();
            $table->foreignId('process_id')->constrained('processes')->onDelete('restrict');
            $table->foreignId('output_item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('input_item_id')->constrained('items')->onDelete('cascade');
            $table->decimal('qty_usage', 16, 8);
            $table->boolean('is_trigger')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boms');
    }
};