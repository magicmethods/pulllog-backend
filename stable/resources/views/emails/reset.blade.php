@php
    $query = http_build_query(['token' => $token, 'type' => 'reset']);
    $tokenUrl = rtrim(config('app.frontend_url'), '/') . '/auth/verify?' . $query;
    $mailBody = trans('mail.body_reset', [
        'name' => $userName,
        'code' => $code,
        'tokenUrl' => $tokenUrl,
    ], $lang);
@endphp

<strong>{{ e(__('mail.greeting', ['name' => $userName], $lang)) }}</strong>

<p>{{ __('mail.reset_intro', [], $lang) }}</p>

{!! nl2br(__('mail.reset_code', ['code' => $code], $lang)) !!}

<p>{{ __('mail.reset_howto', [], $lang) }}</p>

<p>
    <a href="{{ $tokenUrl }}">{{ $tokenUrl }}</a>
</p>

<p style="font-size: smaller; color: #e55;">
    {{ __('mail.abort_notice', [], $lang) }}<br>
    {{ __('mail.expires_notice', [], $lang) }}
</p>

<hr>

{!! nl2br(__('mail.signature', [], $lang)) !!}
