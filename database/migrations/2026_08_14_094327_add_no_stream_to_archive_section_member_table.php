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
            // 「配信なしで参加を確認済み」を表す。video_urlが空欄なだけの未登録と区別するため。
            $table->boolean('no_stream')->default(false)->after('video_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_section_member', function (Blueprint $table) {
            $table->dropColumn('no_stream');
        });
    }
};
