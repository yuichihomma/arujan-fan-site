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
        Schema::create('amongus_regulations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('amongus_record_id')
                ->constrained('amongus_records')
                ->onDelete('cascade');

            // normal = 通常レギュレーション
            // changed = 途中変更後レギュレーション
            $table->string('phase', 20);

            $table->foreignId('crew_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->unsignedInteger('crew_count')->nullable();

            $table->foreignId('impostor_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->unsignedInteger('impostor_count')->nullable();

            $table->foreignId('neutral_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->unsignedInteger('neutral_count')->nullable();

            $table->timestamps();
        });

        Schema::table('amongus_records', function (Blueprint $table) {
            $table->string('regulation_change', 20)->default('none')->after('archive_section_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amongus_records', function (Blueprint $table) {
            $table->dropColumn('regulation_change');
        });

        Schema::dropIfExists('amongus_regulations');
    }
};