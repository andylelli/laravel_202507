<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\Email;

class GetEmailController extends Controller
{
    public function sendEmail(Request $request)
    {
        $userName = "Andy";
        $userEmail = "andylelli@yahoo.com";

        Mail::to($userEmail)->send(new Email($userName));

        return response()->json(['message' => 'Welcome email sent!']);
    }
}
