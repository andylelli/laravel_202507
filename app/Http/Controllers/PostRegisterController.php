<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\_User;
use App\Classes\Traits\General;

class PostRegisterController extends Controller
{
    use General;

	public function postRegister(Request $request)
	{

		$firstname = $request->firstname;
		$lastname = $request->lastname;
		$email = $request->email;
		$password = $request->password;
		$code = $request->code;


        //$this->writeToLog($firstname);
		//$this->writeToLog($lastname);
		//$this->writeToLog($email);
        //$this->writeToLog($password);
        //$this->writeToLog($code);

		$user = new _User();

        $result = $user->registration($firstname, $lastname, $email, $password, $code);
        $this->writeToLog(print_r($result, true));
        if($result['status'] == 'success') {

            return response()->json($result, 200);

        }
        else {

            return response()->json($result, 400);

        }
	}
}
