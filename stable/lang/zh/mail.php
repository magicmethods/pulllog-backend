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
    'subject_signup' => '【PullLog】请确认您的邮箱地址',
    'subject_reset'  => '【PullLog】密码重置指南',
    'greeting'       => ':name 您好',
    'signup_intro'   => '感谢您注册 PullLog。',
    'signup_howto'   => '要完成注册，请点击以下链接进行邮箱验证。',
    'reset_intro'    => '我们已收到您重置 PullLog 账户密码的请求。',
    'reset_code'     => <<<TEXT
-------------------------------------
【验证码】
:code
-------------------------------------
TEXT,
    'reset_howto'    => '您可以通过以下链接继续进行密码重置。',
    'abort_notice'   => '※如果您未发出此请求，请忽略此邮件。',
    'expires_notice' => '※为了安全起见，该链接的有效期为 24 小时。',
    'signature'      => <<<TEXT
※此邮件由发送专用地址（noreply@pulllog.net）自动发送。  
如有疑问，请联系技术支持。

技术支持: support@pulllog.net  
官方网站: https://pulllog.net/

感谢您的使用。

PullLog 运营团队
TEXT,
    // Others
];