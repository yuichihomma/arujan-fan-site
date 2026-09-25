<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amongus_analysis_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_section_id')->constrained('archive_sections')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('video_url', 2048);
            $table->unsignedInteger('match_number')->nullable();
            $table->unsignedInteger('video_timestamp_seconds')->nullable();
            $table->string('video_timestamp_label')->nullable();
            $table->dateTime('estimated_real_time')->nullable();
            $table->string('member_name')->nullable();
            $table->string('role_name')->nullable();
            $table->string('result')->nullable();
            $table->string('win_side')->nullable();
            $table->text('evidence_text')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status')->default('pending');
            $table->text('memo')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['archive_section_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index('match_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amongus_analysis_drafts');
    }
};
