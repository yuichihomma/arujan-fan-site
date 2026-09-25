<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amongus_records', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('regulation_change_match_number');
        });
    }

    public function down(): void
    {
        Schema::table('amongus_records', function (Blueprint $table) {
            $table->dropColumn('is_completed');
        });
    }
};