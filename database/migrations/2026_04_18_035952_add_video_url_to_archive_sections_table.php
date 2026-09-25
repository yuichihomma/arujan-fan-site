<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_sections', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('section_title');
        });
    }

    public function down(): void
    {
        Schema::table('archive_sections', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};