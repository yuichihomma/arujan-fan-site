<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $this->call([
            GameSeeder::class,
            OnedayarchiveSeeder::class,
            CommentSeeder::class,
            AdminSeeder::class,
            MemberSeeder::class,
            RoleSeeder::class,
        ]);
    }
}