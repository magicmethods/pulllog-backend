<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuthToken;
use App\Models\Plan;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PasswordController extends Controller
{
    /**
     * パスワードリセットリクエスト (POST /auth/password)
     */
    public function requestReset(Request $request): JsonResponse
    {

    }
    /**
     * パスワードリセット (PUT /auth/password)
     */
    public function reset(Request $request): JsonResponse
    {
        
    }

}