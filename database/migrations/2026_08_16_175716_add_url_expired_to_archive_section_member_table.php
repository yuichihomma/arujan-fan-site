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
        Schema::table('archive_section_member', function (Blueprint $table) {
            // 配信はしたが、プラットフォーム側の仕様（Twitchの60日VOD失効等）でアーカイブURLが
            // 提示できないことを確認済みであることを表す。no_stream（配信自体が無かった）と区別する。
            $table->boolean('url_expired')->default(false)->after('no_stream');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_section_member', function (Blueprint $table) {
            $table->dropColumn('url_expired');
        });
    }
};
