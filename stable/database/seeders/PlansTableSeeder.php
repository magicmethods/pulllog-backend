<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plans')->insertOrIgnore([
            [
                'name' => 'Free',
                'description' => 'Free plan limited to minimal usage.',
                'max_apps' => 5,
                'max_app_name_length' => 30,
                'max_app_desc_length' => 400,
                'max_log_tags' => 3,
                'max_log_tag_length' => 22,
                'max_log_text_length' => 250,
                'max_logs_per_app' => -1,
                'max_storage_mb' => 100,
                'price_per_month' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Standard',
                'description' => 'Standard plan for comfortable use with no ads.',
                'max_apps' => 10,
                'max_app_name_length' => 30,
                'max_app_desc_length' => 400,
                'max_log_tags' => 5,
                'max_log_tag_length' => 22,
                'max_log_text_length' => 250,
                'max_logs_per_app' => -1,
                'max_storage_mb' => 300,
                'price_per_month' => 480,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Premium',
                'description' => 'Premium plans give you unlimited access to our advanced features.',
                'max_apps' => 50,
                'max_app_name_length' => 30,
                'max_app_desc_length' => 400,
                'max_log_tags' => 10,
                'max_log_tag_length' => 22,
                'max_log_text_length' => 250,
                'max_logs_per_app' => -1,
                'max_storage_mb' => 1024,
                'price_per_month' => 980,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
