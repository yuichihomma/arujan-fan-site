<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amongus_match_member_results', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('member_id')
                ->constrained('roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('amongus_match_member_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};