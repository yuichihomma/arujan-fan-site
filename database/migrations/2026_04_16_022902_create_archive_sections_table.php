<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onedayarchive_id')->constrained('onedayarchives')->onDelete('cascade');
            $table->string('section_type')->nullable();
            $table->string('game_genre')->nullable();
            $table->string('section_title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_sections');
    }
};