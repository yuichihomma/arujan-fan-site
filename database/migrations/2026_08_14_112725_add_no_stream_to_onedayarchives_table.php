<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('onedayarchives', function (Blueprint $table) {
            // 「この日は配信なしと確認済み」を表す。未登録（未確認）と区別するため。
            $table->boolean('no_stream')->default(false)->after('event_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onedayarchives', function (Blueprint $table) {
            $table->dropColumn('no_stream');
        });
    }
};
