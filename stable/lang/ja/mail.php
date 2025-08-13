<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Messages Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during various messages that we
    | need to display to the user. You are free to modify these language lines
    | according to your application's requirements.
    |
    */
    'subject_signup' => '【PullLog】メールアドレスのご確認',
    'subject_reset'  => '【PullLog】パスワード再設定のご案内',
    'greeting'       => ':name 様',
    'signup_intro'   => 'PullLogへのご登録ありがとうございます。',
    'signup_howto'   => 'ご登録を完了するには、下記のリンクをクリックしてメールアドレス認証を行ってください。',
    'reset_intro'    => 'PullLogアカウントのパスワード再設定リクエストを受け付けました。',
    'reset_code'     => <<<TEXT
-------------------------------------
【認証コード】
:code
-------------------------------------
TEXT,
    'reset_howto'    => '下記リンクよりパスワード再設定を続行できます。',
    'abort_notice'   => '※このメールにお心当たりがない場合は、破棄してください。',
    'expires_notice' => '※セキュリティのため、このリンクの有効期限は24時間です。',
    'signature'      => <<<TEXT
※このメールは送信専用アドレス（noreply@pulllog.net）から自動送信されています。  
ご不明な点があればサポートまでお問い合わせください。

サポート: support@pulllog.net  
公式サイト: https://pulllog.net/

ご利用ありがとうございます。

PullLog運営チーム
TEXT,
    'body_signup' => <<<TEXT
:name 様

PullLogへのご登録ありがとうございます。

ご登録を完了するには、下記のリンクをクリックしてメールアドレス認証を行ってください。

:tokenUrl

※このメールにお心当たりがない場合は、破棄してください。

---
※このメールは送信専用アドレス（noreply@pulllog.net）から自動送信されています。  
ご不明な点があればサポートまでお問い合わせください。

サポート: support@pulllog.net  
公式サイト: https://pulllog.net/

ご利用ありがとうございます。

PullLog運営チーム
TEXT,
    'body_reset' => <<<TEXT
:name 様

PullLogアカウントのパスワード再設定リクエストを受け付けました。

-------------------------------------
【認証コード】
:code
-------------------------------------

もしくは、下記リンクよりパスワード再設定を続行できます。

:tokenUrl

※このメールにお心当たりがない場合は、破棄してください。
セキュリティのため、このリンクの有効期限は24時間です。

---
※このメールは送信専用アドレス（noreply@pulllog.net）から自動送信されています。
ご不明な点があればサポートまでお問い合わせください。

サポート: support@pulllog.net
公式サイト: https://pulllog.net/

ご利用ありがとうございます。

PullLog運営チーム
TEXT,

];