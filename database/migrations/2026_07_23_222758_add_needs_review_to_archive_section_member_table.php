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
            $table->boolean('needs_review')->default(false)->after('video_url');
            $table->string('review_reason')->nullable()->after('needs_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_section_member', function (Blueprint $table) {
            $table->dropColumn(['needs_review', 'review_reason']);
        });
    }
};
