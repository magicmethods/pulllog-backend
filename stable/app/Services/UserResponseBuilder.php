<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class UserResponseBuilder
{
    /**
     * ユーザー情報をAPIレスポンス用に整形して返却
     */
    public static function build(User $user): array
    {
        // Eager Load推奨
        $user->loadMissing('plan');
        $plan = $user->plan;
        // プラン制限（型合わせ＆バイト単位変換）
        $planLimits = $plan ? [
            'maxApps' => $plan->max_apps,
            'maxAppNameLength' => $plan->max_app_name_length,
            'maxAppDescriptionLength' => $plan->max_app_desc_length,
            'maxLogTags' => $plan->max_log_tags,
            'maxLogTagLength' => $plan->max_log_tag_length,
            'maxLogTextLength' => $plan->max_log_text_length,
            'maxLogsPerApp' => $plan->max_logs_per_app,
            // max_storage_mb(プラン) → maxStorage(バイト)
            'maxStorage' => $plan->max_storage_mb * 1024 * 1024,
            // maxLogSizeはPlanテーブルに現状は該当フィールドがない
        ] : [];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url ?? null,
            'roles' => $user->roles ?? ['user'],
            'plan' => $plan->name ?? 'free',
            'plan_expiration' => $user->plan_expiration
                ? Carbon::parse($user->plan_expiration)->toDateString()
                : null,
            'plan_limits' => $planLimits,
            'language' => $user->language,
            'theme' => $user->theme ?? 'default',
            'home_page' => $user->home_page ?? null,
            'created_at' => Carbon::parse($user->created_at)->toIso8601String(),
            'updated_at' => Carbon::parse($user->updated_at)->toIso8601String(),
            'last_login' => $user->last_login
                ? Carbon::parse($user->last_login)->toIso8601String()
                : null,
            'last_login_ip' => $user->last_login_ip ?? null,
            'last_login_user_agent' => $user->last_login_ua ?? null,
            'is_deleted' => (bool)($user->is_deleted ?? false),
            'is_verified' => (bool)($user->is_verified ?? false),
            'unread_notifications' => $user->unread_notices ?? [],
        ];
    }
}
