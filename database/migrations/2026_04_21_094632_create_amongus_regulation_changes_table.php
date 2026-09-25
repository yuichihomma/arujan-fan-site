<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amongus_regulation_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('amongus_record_id')
                ->constrained('amongus_records')
                ->cascadeOnDelete();

            $table->string('action', 20); // add / remove

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->unsignedInteger('count');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amongus_regulation_changes');
    }
};