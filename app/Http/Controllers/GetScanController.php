<?php

namespace App\Http\Controllers;
use DB;
use Illuminate\Http\Request;
use App\Classes\Scan;
use App\Classes\Traits\General;

class GetScanController extends Controller
{
    use General;

	public function getScan($type, $eventid, $id, $value, $email, $uniqueid, $token)
	{

		$type = filter_var($type, FILTER_SANITIZE_ADD_SLASHES);
        $eventid = filter_var($eventid, FILTER_SANITIZE_ADD_SLASHES);
        $id = filter_var($id, FILTER_SANITIZE_ADD_SLASHES);
		$email = filter_var($email, FILTER_SANITIZE_ADD_SLASHES);
		$uniqueid = filter_var($uniqueid, FILTER_SANITIZE_ADD_SLASHES);
		$token = filter_var($token, FILTER_SANITIZE_ADD_SLASHES);
		$value = filter_var($value, FILTER_SANITIZE_ADD_SLASHES);

        //check QR code is valid
        $scan = new Scan();
        $scan_check = $scan->QR_scan_check(urldecode($email), $eventid);

        if ($scan_check['status'] == 'success') {

            $guestid = $scan_check['guest_id'];

            //SCOREBOARD
            if($type == 'scoreboard') {
                $scoreboard_update = $scan->QR_scoreboard_update($uniqueid, $id, $value, $eventid);
                if ($scoreboard_update == true) {
                    $response = array(
                        'status' => 'success',
                    );
                }
                else{
                    $response = array(
                        'status' => 'fail',
                        'message' => 'Submission failed'
                    );
                }
            }
            else {
                $response = array(
                    'status' => 'fail',
                    'message' => 'No item found to update'
                );
            }
        }
        else{
            $response = array(
                'status' => 'fail',
                'message' => $scan_check['message']
            );
        }

        return response()->json($response, 201);
	}
}
