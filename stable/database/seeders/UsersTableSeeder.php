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
                'password' => bcrypt('ms~XXZtMcFP)Kn.iij9t'),// 登録後変更必須
                'name' => '管理者',
                'avatar_url' => null,
                'roles' => json_encode(['admin', 'user']),
                'plan_id' => 3, // Premium
                'plan_expiration' => now()->addYear(),
                'language' => 'ja',
                'theme' => 'light',
                'home_page' => '/apps',
                'last_login' => now(),
                'last_login_ip' => null,
                'last_login_ua' => null,
                'is_deleted' => false,
                'is_verified' => true,
                'remember_token' => null,
                'unread_notices' => json_encode([]),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'email' => 'demo@pulllog.net',
                'password' => bcrypt('#Mmh2_GqtkJ-k+RwCd+('),// 登録後適宜変更
                'name' => 'Demo',
                'avatar_url' => null,
                'roles' => json_encode(['user', 'demo']),
                'plan_id' => 4, // Demo
                'plan_expiration' => now()->addYear(),
                'language' => 'en',
                'theme' => 'light',
                'home_page' => '/apps',
                'last_login' => now(),
                'last_login_ip' => null,
                'last_login_ua' => null,
                'is_deleted' => false,
                'is_verified' => true,
                'remember_token' => null,
                'unread_notices' => json_encode([]),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'email' => 'ka2bowy@gmail.com',
                'password' => bcrypt('3ys3fVL+9E9A'),// 登録後適宜変更
                'name' => 'DEMO',
                'avatar_url' => null,
                'roles' => json_encode(['user']),
                'plan_id' => 1, // Free
                'plan_expiration' => now()->addYear(),
                'language' => 'en',
                'theme' => 'light',
                'home_page' => '/apps',
                'last_login' => now(),
                'last_login_ip' => null,
                'last_login_ua' => null,
                'is_deleted' => false,
                'is_verified' => true,
                'remember_token' => null,
                'unread_notices' => json_encode([]),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // 必要に応じて他のユーザーも
        ]);
    }
}
