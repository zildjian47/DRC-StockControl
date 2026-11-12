<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->timestamp('date_time')->useCurrent();
            
            // Relasi ke barang
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            
            // Relasi ke user (Siapa yang melakukan koreksi) - Diubah dari 'username' menjadi 'user_id'
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); 
            
            // Angka snapshot murni (Bukan FK)
            $table->decimal('qty_system', 10, 2);
            $table->decimal('qty_actual', 10, 2);
            $table->decimal('qty_diff', 10, 2);
            
            $table->string('adjustment_type'); // enum: 'LOSS', 'SURPLUS', 'STOCK OPNAME', 'FOUND'
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};