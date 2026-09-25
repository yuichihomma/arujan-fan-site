<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\User;
use App\Models\Onedayarchive;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::first();
        $onedayarchive = Onedayarchive::first();

        Comment::create([
            'user_id' => $user->id,
            'onedayarchive_id' => $onedayarchive->id,
            'content' => 'テストコメントです！',
        ]);
    }
}