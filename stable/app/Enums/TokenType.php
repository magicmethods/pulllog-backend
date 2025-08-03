<?php

namespace App\Enums;

enum TokenType: string
{
    case Signup = 'signup';
    case Reset = 'reset';
    case Remember = 'remember';
}
