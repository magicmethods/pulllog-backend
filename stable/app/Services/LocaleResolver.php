<?php

namespace App\Services;

class LocaleResolver
{
    /**
     * Supported languages.
     *
     * @var array<string>
     */
    protected static array $supportedLanguages = ['ja', 'en', 'zh'];

    /**
     * Resolve the locale based on the request and user preferences.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User|null $user
     * @return string
     */
    public static function resolve($request, $user = null): string
    {
        // Cookie（pulllog-lang）
        $lang = $request->cookie('pulllog-lang');
        if ($lang && in_array($lang, self::$supportedLanguages)) return $lang;

        // ユーザー
        if ($user && in_array($user->language ?? null, self::$supportedLanguages)) return $user->language;

        // デフォルト
        return 'en';
    }
}
