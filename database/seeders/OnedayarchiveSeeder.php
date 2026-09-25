<?php

namespace Database\Seeders;

use App\Models\Onedayarchive;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OnedayarchiveSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
    Onedayarchive::updateOrCreate(
    ['event_date' => '2026-04-14'],
    [
        'official_title' => '第1回 アルジャン',
        'official_video_url' => 'https://example.com',
        'description' => 'テスト用のイベントです',
    ]
);
    }
}