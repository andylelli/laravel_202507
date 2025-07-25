<?php

namespace App\Classes;
use App\Classes\Traits\General;
use DB;
use Exception;

class Scan extends Project{

    /**
    * QR_scan_check. Check to see if the QR if valid or if no valid QR code is needed   *
    * @param string $email                                                              *
    * @param string $token                                                              *
    * @param string $eventid                                                            *
    * @return string success / fail                                                     *
    * @return string $guest id                                                          *
    * @return string $event id                                                          *
    */
    public function QR_scan_check($email, $eventid){

            $results = DB::table('guest')
    		->where('guest_email' ,'=', $email)
            ->where('guest_eventid' ,'=', $eventid)
    		->get();

            $guest_id = $results[0]->guest_id;
            $guest_role = $results[0]->guest_role;
            $guest_eventid = $results[0]->guest_eventid;

            if($eventid == $guest_eventid && $guest_id > 0) {

                $response = array(
                    'status' => 'success',
                    'guest_id' => $guest_id,
                    'guest_role' => $guest_role,
                    'guest_eventid' => $guest_eventid
                );

                return $response;
            }
            else {
                $response = array(
                    'status' => 'fail',
                    'message' => 'QR code not recognised'
                );

                return $response;
            }
    }

    public function QR_scoreboard_update($uniqueid, $scoreboardid, $value, $eventid){

        try {
    		$results = DB::table('scoreboardscore')
    		->where('scoreboardscore_guestid' ,'=', $uniqueid)
    		->where('scoreboardscore_scoreboardid' ,'=', $scoreboardid)
    		->get();
        }
        catch(Exception $e) {
           $error = $e->getMessage();
           $this->writeToLog($error);
        }

		$count = $results->count();

        if($count == 0) {
            $insert = $this->scoreboardscore_insert($uniqueid, $scoreboardid, $value, $eventid);
            if($insert == true) {
                return true;
            }
        }
        elseif($count == 1)  {
            $update = $this->scoreboardscore_update($uniqueid, $scoreboardid, $value);
            if($update == true) {
                return true;
            }
        }
        else {
            return false;
        }
    }

    /* Insert new entry into scoreboardscore                *
    * @param string $user_id                                *
    * @param string $scoreboardid                           *
    * @param string $value                                  *
    * @return boolean.                                      *
    **/
    private function scoreboardscore_insert($uniqueid, $scoreboardid, $value, $eventid){

        $uxtime = time() + 60;

        try {
            $result = DB::table('scoreboardscore')->insert([
            	'scoreboardscore_guestid' => $uniqueid,
            	'scoreboardscore_count' => $value,
            	'scoreboardscore_scoreboardid' => $scoreboardid,
            	'scoreboardscore_eventid' => $eventid,
            	'scoreboardscore_uxtime' => $uxtime
            ]);
        }
        catch(Exception $e) {
           $error = $e->getMessage();
           $this->writeToLog($error);
        }

		return $result;

    }

    /* Update existing entry in scoreboardscore             *
    * @param string $user_id                                *
    * @param string $scoreboardid                           *
    * @param string $value                                  *
    * @return boolean.                                      *
    **/
    private function scoreboardscore_update($uniqueid, $scoreboardid, $value){

        try {
    		$result = DB::table('scoreboardscore')
    		->where('scoreboardscore_guestid' ,'=', $uniqueid)
    		->where('scoreboardscore_scoreboardid' ,'=', $scoreboardid)
    		->get();
        }
        catch(Exception $e) {
           $error = $e->getMessage();
           $this->writeToLog($error);
        }

        $new_count = $result[0]->scoreboardscore_count + $value;
        $uxtime = time() + 60;

        try {
    		$result = DB::table('scoreboardscore')
            ->where('scoreboardscore_guestid', $uniqueid)
            ->where('scoreboardscore_scoreboardid', $scoreboardid)
            ->update([
                'scoreboardscore_count' => $new_count,
                'scoreboardscore_uxtime' => $uxtime
    		]);
        }
        catch(Exception $e) {
           $error = $e->getMessage();
           $this->writeToLog($error);
        }

		return $result;
    }

}
