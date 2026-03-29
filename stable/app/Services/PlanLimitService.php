<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PlanLimitService
{
    public function getGalleryLimitsForUser(int $userId): array
    {
        $row = DB::table('users')
            ->join('plans', 'users.plan_id', '=', 'plans.id')
            ->where('users.id', $userId)
            ->select([
                'plans.max_gallery_mb',
                'plans.max_upload_mb_per_file',
                'plans.external_storage_allowed',
                'plans.transcode_webp',
            ])->first();

        if (!$row) {
            return [
                'max_gallery_bytes' => 300 * 1024 * 1024,
                'max_upload_bytes_per_file' => 20 * 1024 * 1024,
                'external_storage_allowed' => false,
                'transcode_webp' => true,
            ];
        }

        return [
            'max_gallery_bytes' => (int) $row->max_gallery_mb * 1024 * 1024,
            'max_upload_bytes_per_file' => (int) $row->max_upload_mb_per_file * 1024 * 1024,
            'external_storage_allowed' => (bool) $row->external_storage_allowed,
            'transcode_webp' => (bool) $row->transcode_webp,
        ];
    }
}
