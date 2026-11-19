<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_code');
            $table->string('company_name');
            $table->string('type_company');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_code', 'type_company']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};