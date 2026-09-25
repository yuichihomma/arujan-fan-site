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
            // 未着手(null) → 仮完了(tentative) → 完了(done) の3段階。
            // 保存(update)や内容編集では変更されず、編集画面の専用ボタンでのみ手動で切り替える。
            $table->string('status')->nullable()->after('no_stream');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onedayarchives', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
