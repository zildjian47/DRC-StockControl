<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AUDIT_TABLES = [
        'inbounds',
        'material_outs',
        'material_returns',
        'productions',
        'deliveries',
        'return_to_suppliers',
        'production_ngs',
    ];

    public function up(): void
    {
        // 1. Tambah penanda batch kejadian retur agar 1 Lot dapat melakukan Multi-Return selama ON PROGRESS
        Schema::table('material_returns', function (Blueprint $table) {
            $table->string('return_batch', 50)->nullable()->index()->after('lot_number');
        });

        // 2. Tambah status qty_check pada production_ngs agar seragam dengan 6 transaksi koreksi lainnya
        Schema::table('production_ngs', function (Blueprint $table) {
            $table->string('qty_check')->default('TERCATAT')->after('problem_reason');
        });

        // 3. Tambah kolom Audit Trail koreksi pada 7 tabel transaksi
        foreach (self::AUDIT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('qty_original', 10, 2)->nullable()->after('qty_check');
                $table->foreignId('corrected_by')
                    ->nullable()
                    ->after('qty_original')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('corrected_at')->nullable()->after('corrected_by');
                $table->string('correction_notes', 500)->nullable()->after('corrected_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::AUDIT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['corrected_by']);
                $table->dropColumn([
                    'qty_original',
                    'corrected_by',
                    'corrected_at',
                    'correction_notes',
                ]);
            });
        }

        Schema::table('production_ngs', function (Blueprint $table) {
            $table->dropColumn('qty_check');
        });

        Schema::table('material_returns', function (Blueprint $table) {
            $table->dropIndex(['return_batch']);
            $table->dropColumn('return_batch');
        });
    }
};