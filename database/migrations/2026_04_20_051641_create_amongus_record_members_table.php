<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amongus_record_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amongus_record_id')->constrained('amongus_records')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['amongus_record_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amongus_record_members');
    }
};