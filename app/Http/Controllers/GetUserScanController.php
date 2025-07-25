<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Hunt;
use App\Classes\Traits\General;

class GetUserScanController extends Controller
{
    use General;

	public function getUserScan($table, $tableid, $itemid, $token)
	{
        // HUNT
        if($table == 'hunt') {

            $hunt = new Hunt();
            $valid = $hunt->validate_huntitem_token($tableid, $itemid, $token);

            if ($valid === true) {
                $response = array(
                    'status' => 'success',
                    'huntitemid' => $itemid,
                    'validated' => true,
                    'message' => 'QR Code Found!'
                );
                return response()->json($response, 200);
            }
            else{
                $response = array(
                    'status' => 'fail',
                    'message' => 'QR Code not recognised!'
                );
                return response()->json($response, 400);
            }
        }
        else {
            $response = array(
                'status' => 'fail',
                'message' => 'QR Code not recognised!'
            );
            return response()->json($response, 400);
        }
	}
}
