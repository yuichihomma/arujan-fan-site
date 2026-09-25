<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 全メンバーの生配信アーカイブを、アルジャンタグの有無に関わらずそのまま貯めておく生データ置き場。
        // TwitchのVOD（約60日で失効）やOPENREC（最新20件のみ）は後から遡れないため、
        // 定期取り込みで消える前に記録しておき、仕分け後にarchive_section_memberへ流す。
        Schema::create('stream_archives', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('video_id');
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('url');
            $table->string('title')->default('');
            $table->text('description')->nullable();
            $table->dateTime('started_at');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->date('event_date');
            $table->string('estimated_section_type')->nullable();
            $table->boolean('has_arujan_tag')->default(false);
            $table->timestamps();

            $table->unique(['platform', 'video_id']);
            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_archives');
    }
};
