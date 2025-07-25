<?php

namespace App\Http\Controllers;

use DB;
use App\Classes\Traits\General;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class PostInstallController extends Controller
{
    use General;

    public function postInstall(Request $request)
    {
        $data = $request->all();
        $id = $data['event_id'];
        $uxtime = $this->unixTime();

        try {
            $results = DB::table("install")
                ->where('install_eventid', $id)
                ->increment('install_count', 1, [
                    'install_uxtime' => $uxtime
                ]);

            if ($results > 0) {
                return response()->json([
                    'status' => 'success'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Error updating table'
                ], 400);
            }
        } catch (Exception $e) {
            // Optionally log the error
            $this->writeToLog('Install update error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}

