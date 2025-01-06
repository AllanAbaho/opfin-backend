<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // First, create the 'Super' user
        User::create([
            'name' => 'Super User',
            'phone' => '+256776911458',
            'role' => 'Super',
            'institution_id' => null,
            'is_admin' => false,
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),
        ]);
        // Then, create the 'Member' users
        User::factory()->count(9)->create();
    }
}
