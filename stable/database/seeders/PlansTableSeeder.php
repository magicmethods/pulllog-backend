<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $plans = [
            [
                'name' => 'Free',
                'description' => 'Free plan limited to minimal usage.',
                'max_apps' => 10,
                'max_app_name_length' => 30,
                'max_app_desc_length' => 400,
                'max_log_tags' => 3,
                'max_log_tag_length' => 22,
                'max_log_text_length' => 250,
                'max_logs_per_app' => -1,
                'max_gallery_mb' => 300,
                'max_upload_mb_per_file' => 20,
                'external_storage_allowed' => false,
                'transcode_webp' => true,
                'max_storage_mb' => 300,
                'price_per_month' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Standard',
                'description' => 'Standard plan for comfortable use with no ads.',
                'max_apps' => 20,
                'max_app_name_length' => 30,
                'max_app_desc_length' => 400,
                'max_log_tags' => 5,
                'max_log_tag_length' => 22,
                'max_log_text_length' => 250,
                'max_logs_per_app' => -1,
                'max_gallery_mb' => 500,
                'max_upload_mb_per_file' => 25,
                'external_storage_allowed' => false,
                'transcode_webp' => true,
                'max_storage_mb' => 500,
                'price_per_month' => 480,
                'is_active' => true,
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
                'max_gallery_mb' => 1024,
                'max_upload_mb_per_file' => 40,
                'external_storage_allowed' => true,
                'transcode_webp' => true,
                'max_storage_mb' => 1024,
                'price_per_month' => 980,
                'is_active' => true,
            ],
            [
                'name' => 'Demo',
                'description' => 'Demo plan for trial purposes.',
                'max_apps' => 5,
                'max_app_name_length' => 30,
                'max_app_desc_length' => 400,
                'max_log_tags' => 3,
                'max_log_tag_length' => 22,
                'max_log_text_length' => 250,
                'max_logs_per_app' => -1,
                'max_gallery_mb' => 100,
                'max_upload_mb_per_file' => 10,
                'external_storage_allowed' => false,
                'transcode_webp' => true,
                'max_storage_mb' => 100,
                'price_per_month' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            $existing = DB::table('plans')->where('name', $plan['name'])->first();

            if ($existing) {
                DB::table('plans')
                    ->where('id', $existing->id)
                    ->update(array_merge($plan, [
                        'updated_at' => $now,
                    ]));
            } else {
                DB::table('plans')->insert(array_merge($plan, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
