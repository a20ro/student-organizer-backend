<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'admin@studentorganizer.com';
        
        if (!User::where('email', $email)->exists()) {
            User::create([
                'name' => 'System Admin',
                'email' => $email,
                'password' => Hash::make('AdminPassword123!'),
                'role' => 'super_admin',
                'status' => 'active',
            ]);
        }
    }
}
