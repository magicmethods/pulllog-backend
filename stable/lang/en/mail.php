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
    'body_signup'    => "Thank you for registering with PullLog.\nPlease verify your email address using the link below.\n:tokenUrl",
    'body_reset'     => "We have received a password reset request.\nYour verification code is: :code\nAlternatively, you can continue the reset process using the link below.\n:tokenUrl",
    'signature'      => "PullLog Team",

    'body_signup' => <<<TEXT
Hello :name,

Thank you for registering with PullLog!

To complete your registration, please verify your email address by clicking the link below:

:tokenUrl

If you did not create a PullLog account, please ignore this email.

---
This email was automatically sent from a no-reply address (noreply@pulllog.net). If you have any questions, please contact support.

Support: support@pulllog.net  
Official Site: https://pulllog.net/

Thank you!

PullLog Team
TEXT,
    'body_reset' => <<<TEXT
Hello :name,

We have received a request to reset your PullLog account password.

-------------------------------------
【Verification Code】
:code
-------------------------------------

Alternatively, you can continue the reset process using the link below.

:tokenUrl

If you did not request a password reset, please ignore this email.
For security reasons, this link will expire in 24 hours.

---
This email was automatically sent from a no-reply address (noreply@pulllog.net). If you have any questions, please contact support.

Support: support@pulllog.net
Official Site: https://pulllog.net/

Thank you for using PullLog.

PullLog Team
TEXT,

];
