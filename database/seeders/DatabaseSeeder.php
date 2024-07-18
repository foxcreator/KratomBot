<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Promocode;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

//        User::factory()->create([
//            'name' => 'Kratom Admin',
//            'email' => 'admin@admin.com',
//            'password' => 'admin',
//        ]);

        $members = Member::factory(50)->create();

        // Для каждого члена создаем промокод
        $members->each(function ($member) {
            Promocode::factory()->create([
                'member_id' => $member->id,
            ]);
        });
    }
}
