<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // メインチャンネルとは別に、アルジャン配信専用のYouTubeチャンネルを持つメンバー用。
            // 登録しておくと、アーカイブ抽出時にこのチャンネルも自動で検索対象になる。
            $table->string('arujan_youtube_url')->nullable()->after('youtube_channel_id');
            $table->string('arujan_youtube_channel_id')->nullable()->after('arujan_youtube_url');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['arujan_youtube_url', 'arujan_youtube_channel_id']);
        });
    }
};
