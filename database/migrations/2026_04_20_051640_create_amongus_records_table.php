<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amongus_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_section_id')->constrained('archive_sections')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('archive_section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amongus_records');
    }
};