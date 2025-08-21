<?php

return [
    // デモユーザーとみなすメールアドレス（単一想定）
    'demo_email' => env('DEMO_EMAIL', 'demo@pulllog.net'),

    // 追加で個別IDを指定したい場合
    'demo_user_ids' => array_filter(explode(',', (string) env('DEMO_USER_IDS', ''))),
];
