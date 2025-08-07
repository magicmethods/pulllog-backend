<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\LocaleResolver;
use App\Services\UserResponseBuilder;
//use App\Models\User;
//use App\Models\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
//use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
//use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{
    /**
     * GET /user
     * ユーザー情報を取得
     */
    public function userGet(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        $userResponse = UserResponseBuilder::build($user);

        return response()->json([
            'state' => 'success',
            'message' => trans('messages.user.profile_get_success', [], $lang),
            'user' => $userResponse,
        ]);
    }
    /**
     * PUT /user/update
     * ユーザー情報を更新
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        /*
        // プラン情報取得（バリデーション閾値に使用する場合）
        $user->loadMissing('plan');
        $plan = $user->plan;
        if (!$plan) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.user.plan_not_found', [], $lang),
                'user' => null,
            ], 400);
        }
        */

        // バリデーションルール
        $rules = [
            'name'      => ['required', 'string', 'max:50'],
            'password'  => ['nullable', 'string', 'min:8'],
            'avatarUrl' => ['nullable', 'string', 'max:512'],
            'language'  => ['required', 'string', 'max:10'],
            'theme'     => ['required', 'string', 'max:20'],
            'homePage'  => ['nullable', 'string', 'max:255'],
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.user.update_validation_failed', ['error' => $validator->errors()->first()], $lang),
                'user' => null,
            ], 422);
        }

        // 値の更新
        $user->name = $request->input('name');
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }
        if ($request->filled('avatarUrl')) {
            $user->avatar_url = $request->input('avatarUrl');
        }
        $user->language = $request->input('language');
        $user->theme = $request->input('theme');
        $user->home_page = $request->input('homePage');
        $user->updated_at = Carbon::now('UTC');

        $user->save();

        // 最新情報をUserResponseBuilderで
        $userResponse = UserResponseBuilder::build($user);

        return response()->json([
            'state' => 'success',
            'message' => trans('messages.user.profile_update_success', [], $lang),
            'user' => $userResponse,
        ]);
    }
    /**
     * DELETE /user
     * ユーザーアカウントを削除
     */
    public function delete(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        // メールアドレスを加工
        $user->email = $user->email . '__deleted_' . Carbon::now('UTC')->format('YmdHis');
        $user->is_deleted = true;
        $user->updated_at = Carbon::now('UTC');
        $user->save();

        // 必要ならここでUserSession::where('user_id', $user->id)->delete(); も可
        // 紐付いている App や Log は削除しない（後バッチ等で対応する）

        return response()->json([
            'state' => 'success',
            'message' => trans('messages.user.profile_deleted', [], $lang),
        ]);
    }
    /**
     * POST /user/avatar
     * ユーザーアバター画像をアップロード
     */
    public function avatar(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        // バリデーション
        $validator = Validator::make($request->all(), [
            'avatar' => [
                'required',
                'file',
                'image',
                'max:1024', // 最大1MB
            ],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.user.avatar_upload_failed', ['error' => $validator->errors()->first()], $lang),
                'user' => null,
            ], 422);
        }

        $file = $request->file('avatar');
        // v3: ImageManager経由で生成・WebP変換
        $manager = new ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
        $img = $manager->read($file)->toWebp(85);

        // ファイル名（ユーザー毎に一意、常に上書き）
        $filename = 'avatar_' . $user->id . '.webp';
        $storagePath = 'avatars/' . $filename;
        $result = Storage::disk('public')->put($storagePath, $img->toString());
        if (!$result) {
            //Log::error("Failed to save avatar image to: $storagePath");
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.user.image_saving_failed', [], $lang),
                'user' => null,
            ], 422);
        }
        //Log::info("Successfully saved avatar image to: $storagePath");

        // 公開URL取得（storage:link で public/storage からアクセスできるようにする）
        $avatarUrl = url(Storage::url('avatars/' . $filename) . '?' . time());

        // Userモデル更新
        $user->avatar_url = $avatarUrl;
        $user->save();

        return response()->json([
            'state' => 'success',
            'user' => [
                'avatarUrl' => $avatarUrl,
            ],
        ]);
    }


}
