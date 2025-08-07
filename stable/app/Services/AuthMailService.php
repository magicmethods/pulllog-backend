<?php

namespace App\Services;

use App\Mail\VerificationOrResetMail;
use Illuminate\Support\Facades\Mail;

class AuthMailService
{
    /**
     * Send verification or reset email.
     * @param User $user
     * @param string $token
     * @param string|null $code
     * @param string $type 'signup' or 'reset'
     * @param string $lang
     */
    public static function send($user, $token, $code = null, $type = 'signup', $lang = 'en')
    {
        Mail::to($user->email)->send(
            new VerificationOrResetMail(
                $user->name,
                $token,
                $code,
                $type,
                $lang
            )
        );
    }
}
