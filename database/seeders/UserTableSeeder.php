<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = DB::table('users')->insert([
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'), // Hashing the password
                'email_verified_at' => now(), // Optional: set to now if you want to mark the email as verified
                'remember_token' => null, // Optional: used for "remember me" functionality
                'created_at' => now(),
                'updated_at' => now(),

            ],
        ]);
    }
}
