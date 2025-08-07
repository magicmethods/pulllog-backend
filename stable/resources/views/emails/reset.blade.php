@php
    $tokenUrl = rtrim(config('app.url'), '/') . '/auth/verify?token=' . $token . '&type=reset';
    $mailBody = trans('mail.body_reset', [
        'name' => $userName,
        'code' => $code,
        'tokenUrl' => $tokenUrl,
    ], $lang);
@endphp

{!! nl2br(e($mailBody)) !!}
