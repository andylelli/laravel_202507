<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Classes\_User;
use App\Classes\Traits\General;



class ValidateToken
{
    use General;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        $token = $request->header('Authorization', '');
		$userid = $request->header('UserID');
		$eventid = $request->header('EventID');
		$role = $request->header('Role');
        $action = $request->header('Login');

        //$this->writeToLog($token);
        //$this->writeToLog($userid);
        //$this->writeToLog($eventid);
        //$this->writeToLog($role);

        $user = new _User();
        $validate = $user->validate_token($token, $eventid, $userid, $role);
        //$validate = true;

        if($validate == true) {
            return $next($request);
        }
        else {
            $response[] = array(
                'status' => 'fail',
                'message' => 'Access denied, sorry'
            );
            return response()->json($response, 403);
        }


    }
}
