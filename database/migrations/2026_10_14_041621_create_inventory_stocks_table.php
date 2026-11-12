<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->decimal('current_stock', 10, 2)->default(0);
            $table->string('status_stock')->default('EMPTY STOCK'); // enum: 'EMPTY STOCK', 'LOW STOCK', 'NORMAL STOCK', 'OVERSTOCK'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};