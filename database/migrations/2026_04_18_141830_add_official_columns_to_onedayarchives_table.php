<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onedayarchives', function (Blueprint $table) {
            $table->string('official_title')->nullable()->after('event_date');
            $table->string('official_video_url')->nullable()->after('official_title');
            $table->string('thumbnail_url')->nullable()->after('official_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('onedayarchives', function (Blueprint $table) {
            $table->dropColumn([
                'official_title',
                'official_video_url',
                'thumbnail_url',
            ]);
        });
    }
};