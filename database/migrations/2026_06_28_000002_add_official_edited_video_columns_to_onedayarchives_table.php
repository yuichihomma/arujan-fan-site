<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onedayarchives', function (Blueprint $table) {
            $table->string('official_edited_title')->nullable()->after('official_video_url');
            $table->string('official_edited_video_url')->nullable()->after('official_edited_title');
        });
    }

    public function down(): void
    {
        Schema::table('onedayarchives', function (Blueprint $table) {
            $table->dropColumn([
                'official_edited_title',
                'official_edited_video_url',
            ]);
        });
    }
};
