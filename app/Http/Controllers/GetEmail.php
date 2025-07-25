<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Mail\Email;

class GetEmail extends Controller
{

    public function getEmail()
    {
        Mail::to('andylelli@yahoo.com')->send(new Mail([
            'name' => 'Demo',
       ]));
    }
}

