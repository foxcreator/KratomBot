<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Promocode;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Kratom Admin',
            'email' => 'puresportdp@gmail.com',
            'password' => 'adminKratom24',
        ]);

//        $members = Member::factory(50)->create();
//
//        $members->each(function ($member) {
//            Promocode::factory()->create([
//                'member_id' => $member->id,
//            ]);
//        });
    }
}
