<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_ngs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('date_time')->useCurrent();
            $table->string('lot_number')->index();
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('qty_ng', 10, 2);
            $table->string('problem_reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_ngs');
    }
};