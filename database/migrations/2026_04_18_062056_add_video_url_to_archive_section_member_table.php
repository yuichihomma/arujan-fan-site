<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_section_member', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('member_id');
        });
    }

    public function down(): void
    {
        Schema::table('archive_section_member', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};