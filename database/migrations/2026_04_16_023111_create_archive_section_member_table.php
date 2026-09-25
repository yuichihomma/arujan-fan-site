<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_section_member', function (Blueprint $table) {
    $table->id();
    $table->foreignId('archive_section_id')->constrained('archive_sections')->onDelete('cascade');
    $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_section_streamer');
    }
};