<?php

namespace Database\Seeders;

use App\Models\Habit;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name'     => 'Player One',
            'email'    => 'player@test.com',
            'password' => bcrypt('password123'),
        ]);

        Habit::insert([
            [
                'user_id'    => $user->id,
                'title'      => 'Morning Run',
                'difficulty' => 1,
                'type'       => 'daily',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id'    => $user->id,
                'title'      => 'Read 30 Pages',
                'difficulty' => 2,
                'type'       => 'daily',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id'    => $user->id,
                'title'      => 'Full Workout',
                'difficulty' => 3,
                'type'       => 'weekly',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
