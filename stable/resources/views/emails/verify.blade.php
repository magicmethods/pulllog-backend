@php
    $query = http_build_query(['token' => $token, 'type' => 'signup']);
    $tokenUrl = rtrim(config('app.frontend_url'), '/') . '/auth/verify?' . $query;
    $mailBody = trans('mail.body_signup', [
        'name' => $userName,
        'tokenUrl' => $tokenUrl,
    ], $lang);
@endphp

<strong>{{ e(__('mail.greeting', ['name' => $userName], $lang)) }}</strong>

<p>{{ __('mail.signup_intro', [], $lang) }}</p>

<p>{{ __('mail.signup_howto', [], $lang) }}</p>

<p>
    <a href="{{ $tokenUrl }}">{{ $tokenUrl }}</a>
</p>

<p style="font-size: smaller; color: #e55;">
    {{ __('mail.abort_notice', [], $lang) }}<br>
    {{ __('mail.expires_notice', [], $lang) }}
</p>

<hr>

{!! nl2br(__('mail.signature', [], $lang)) !!}
