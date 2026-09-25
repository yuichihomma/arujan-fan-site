<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amongus_match_member_results', function (Blueprint $table) {
        $table->id();
        $table->foreignId('amongus_match_id')->constrained('amongus_matches')->cascadeOnDelete();
        $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
        $table->string('result');
        $table->timestamps();

        $table->unique(['amongus_match_id', 'member_id']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('amongus_match_member_results');
    }
};