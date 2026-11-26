<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('ACTIVE')->index()->after('role');
            $table->string('requested_role', 30)->nullable()->after('status');
            $table->foreignId('approved_by')
                ->nullable()
                ->after('requested_role')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // Selaraskan akun lama yang masih memakai role generik 'STAFF' ke 'STAFF_MATERIAL'
        DB::table('users')
            ->where('role', 'STAFF')
            ->update(['role' => 'STAFF_MATERIAL']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'requested_role',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};