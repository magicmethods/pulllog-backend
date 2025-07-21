<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insertOrIgnore([
            [
                'email' => 'admin@pulllog.net',
                'password_hash' => bcrypt('testtest'),
                'name' => '管理者',
                'avatar_url' => null,
                'roles' => json_encode(['admin']),
                'plan_id' => 1,
                'plan_expiration' => now()->addYear(),
                'language' => 'ja',
                'theme' => 'light',
                'home_page' => 'dashboard',
                'last_login' => now(),
                'last_login_ip' => null,
                'last_login_ua' => null,
                'is_deleted' => false,
                'is_verified' => true,
                'unread_notices' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 必要に応じて他のユーザーも
        ]);
    }
}
