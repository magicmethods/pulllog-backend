<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationOrResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $token;
    public string|null $code;
    public string $type; // 'signup' or 'reset'
    public string $lang;

    public function __construct($userName, $token, $code, $type = 'signup', $lang = 'en')
    {
        $this->userName = $userName;
        $this->token = $token;
        $this->code = $code;
        $this->type = $type;
        $this->lang = $lang;
    }

    public function build()
    {
        $subjectKey = $this->type === 'signup' ? 'mail.subject_signup' : 'mail.subject_reset';
        $view = $this->type === 'signup' ? 'emails.verify' : 'emails.reset';

        return $this
            ->subject(trans($subjectKey, [], $this->lang))
            ->view($view)
            ->with([
                'userName' => $this->userName,
                'token'    => $this->token,
                'code'     => $this->code,
                'lang'     => $this->lang,
            ]);
    }
}
