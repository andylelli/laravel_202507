<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Email extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $userEmail;

    public function __construct($userName)
    {
        $this->userName = $userName;
    }

    public function build()
    {
        return $this->subject('Welcome to Our Application')
                    ->view('emails');
    }
}
