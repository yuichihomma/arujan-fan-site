<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('members', function (Blueprint $table) {

        $table->text('description')->nullable()->after('avatar');

        $table->string('youtube_url')->nullable()->after('description');

        $table->string('x_url')->nullable()->after('youtube_url');

        $table->string('twitch_url')->nullable()->after('x_url');

    });
}

public function down(): void
{
    Schema::table('members', function (Blueprint $table) {

        $table->dropColumn([
            'avatar',
            'description',
            'youtube_url',
            'x_url',
            'twitch_url',
        ]);

    });
}
};
