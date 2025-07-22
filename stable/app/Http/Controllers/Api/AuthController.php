<?php

namespace App\Http\Controllers\Api;

use Crell\Serde\SerdeCommon;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Api\DefaultApiInterface;
//use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        private readonly DefaultApiInterface $api,
        private readonly SerdeCommon $serde = new SerdeCommon(),
    )
    {
    }

    /**
     * Operation authAutologinPost
     *
     * 自動ログイン
     *
     */
    public function authAutologinPost(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        try {
            $apiResult = $this->api->authAutologinPost();
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\LoginResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\LoginResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }

        if ($apiResult instanceof App\Models\LoginResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation authLoginPost
     *
     * ユーザーログイン
     *
     */
    public function authLoginPost(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        $loginRequest = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\LoginRequest::class);

        try {
            $apiResult = $this->api->authLoginPost($loginRequest);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\LoginResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\LoginResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }

        if ($apiResult instanceof App\Models\NoContent500) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 500);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation authLogoutPost
     *
     * ユーザーログアウト
     *
     */
    public function authLogoutPost(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        try {
            $apiResult = $this->api->authLogoutPost();
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 500);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation authPasswordPost
     *
     * パスワード再設定リクエスト
     *
     */
    public function authPasswordPost(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        $passwordResetRequest = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\PasswordResetRequest::class);

        try {
            $apiResult = $this->api->authPasswordPost($passwordResetRequest);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\PasswordResetResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\PasswordResetResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }

        if ($apiResult instanceof App\Models\PasswordResetResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation authPasswordPut
     *
     * パスワード再設定・確定
     *
     */
    public function authPasswordPut(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        $passwordUpdateRequest = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\PasswordUpdateRequest::class);

        try {
            $apiResult = $this->api->authPasswordPut($passwordUpdateRequest);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation authRegisterPost
     *
     * アカウント新規登録
     *
     */
    public function authRegisterPost(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        $registerRequest = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\RegisterRequest::class);

        try {
            $apiResult = $this->api->authRegisterPost($registerRequest);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\RegisterResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 201);
        }

        if ($apiResult instanceof App\Models\RegisterResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation authVerifyPost
     *
     * メール認証トークン受付
     *
     */
    public function authVerifyPost(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        $verifyTokenRequest = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\VerifyTokenRequest::class);

        try {
            $apiResult = $this->api->authVerifyPost($verifyTokenRequest);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\VerifyResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }


        // This shouldn't happen
        abort(500);
    }

}
