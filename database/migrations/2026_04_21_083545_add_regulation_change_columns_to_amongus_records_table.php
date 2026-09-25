<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('amongus_records', function (Blueprint $table) {
        $table->unsignedInteger('regulation_change_match_number')
            ->nullable()
            ->after('regulation_change');
    });
}

    public function down(): void
{
    Schema::table('amongus_records', function (Blueprint $table) {
        $table->dropColumn('regulation_change_match_number');
    });
}
};
