<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('archive_sections')
            ->where('section_type', 'external')
            ->update([
                'section_type' => 'special',
                'section_title' => '特別回',
            ]);
    }

    public function down(): void
    {
        DB::table('archive_sections')
            ->where('section_type', 'special')
            ->update([
                'section_type' => 'external',
                'section_title' => '特別会',
            ]);
    }
};
