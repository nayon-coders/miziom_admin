<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(['email'=>'admin@info.com'],[
            'name' => 'Mr. Admin',
            'role_module' => ROLE_SUPER_ADMIN,
            'status' => STATUS_SUCCESS,
            'email_verified' => 1,
            'password' => Hash::make('Pass.321'),
            'unique_code' => makeUniqueId()
        ]);
    }
}
