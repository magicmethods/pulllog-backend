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
    'subject_signup' => '【PullLog】Email Verification',
    'subject_reset'  => '【PullLog】Password Reset Instructions',
    'greeting'       => 'Dear :name,',
    'signup_intro'   => 'Thank you for registering with PullLog.',
    'signup_howto'   => 'To complete your registration, please verify your email address by clicking the link below:',
    'reset_intro'    => 'We have received a request to reset your PullLog account password.',
    'reset_code'     => <<<TEXT
-------------------------------------
【Verification Code】
:code
-------------------------------------
TEXT,
    'reset_howto'    => 'Alternatively, you can continue the reset process using the link below.',
    'abort_notice'   => 'If you did not request this email, please ignore it.',
    'expires_notice' => 'For security reasons, this link will expire in 24 hours.',
    'signature'      => <<<TEXT
This email was automatically sent from a no-reply address (noreply@pulllog.net).
If you have any questions, please contact support.

Support: support@pulllog.net
Official Site: https://pulllog.net/

Thank you for using PullLog.

PullLog Team
TEXT,
    // Others
];
