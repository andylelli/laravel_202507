<?php

namespace App\Classes;

use App\Mail\Email as EmailMailable;
use Illuminate\Support\Facades\Mail;

class Email
{
    public function send_email()
    {
        Mail::to('andylelli@yahoo.com')->send(new EmailMailable([
            'name' => 'Demo',
        ]));
    }
}

