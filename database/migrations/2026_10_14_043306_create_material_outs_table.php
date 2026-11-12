<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_outs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('date_time')->useCurrent();
            $table->foreignId('material_item_id')->constrained('items')->onDelete('restrict');
            $table->foreignId('target_item_id')->constrained('items')->onDelete('restrict');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lot_number')->index();
            $table->decimal('qty', 10, 2);
            $table->string('qty_check')->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_outs');
    }
};