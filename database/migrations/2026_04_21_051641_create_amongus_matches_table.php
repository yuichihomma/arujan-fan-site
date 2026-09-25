<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amongus_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amongus_record_id')->constrained('amongus_records')->cascadeOnDelete();
            $table->unsignedInteger('match_number');
            $table->string('win_side')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->unique(['amongus_record_id', 'match_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amongus_matches');
    }
};