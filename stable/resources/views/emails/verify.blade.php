@php
    $tokenUrl = rtrim(config('app.url'), '/') . '/auth/verify?token=' . $token . '&type=signup';
    $mailBody = trans('mail.body_signup', [
        'name' => $userName,
        'tokenUrl' => $tokenUrl,
    ], $lang);
@endphp

{!! nl2br(e($mailBody)) !!}
