<?php

namespace App\Services\OAuth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GoogleOauthService
{
    private string $tokenEndpoint = 'https://oauth2.googleapis.com/token';
    private string $tokenInfoEndpoint = 'https://oauth2.googleapis.com/tokeninfo';

    /**
     * 認可コードをアクセストークンに交換
     */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        $resp = Http::asForm()->post($this->tokenEndpoint, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ]);

        if (!$resp->ok()) {
            throw new \RuntimeException('Failed to exchange authorization code: ' . $resp->body());
        }

        /** @var array{access_token:string,id_token:string,refresh_token?:string,expires_in?:int,token_type?:string} */
        $data = $resp->json();

        return $data;
    }

    /**
     * id_token を Google 側で検証し、クレームを取得
     */
    public function verifyIdToken(string $idToken): array
    {
        $verify = Http::get($this->tokenInfoEndpoint, ['id_token' => $idToken]);
        if (!$verify->ok()) {
            throw new \RuntimeException('Failed to verify id_token: ' . $verify->body());
        }

        /** @var array $claims */
        $claims = $verify->json();

        $aud = $claims['aud'] ?? null;
        $iss = $claims['iss'] ?? null;
        $email = $claims['email'] ?? null;
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $exp = isset($claims['exp']) ? (int)$claims['exp'] : 0;

        if ($aud !== config('services.google.client_id')) {
            throw new \RuntimeException('Invalid aud in id_token');
        }
        if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
            throw new \RuntimeException('Invalid iss in id_token');
        }
        if ($exp <= Carbon::now('UTC')->timestamp) {
            throw new \RuntimeException('id_token is expired');
        }
        if (!$email || !$emailVerified) {
            throw new \RuntimeException('Email is missing or not verified');
        }

        return $claims;
    }
}
