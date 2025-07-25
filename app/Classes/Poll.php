<?php

namespace App\Classes;
use App\Classes\Traits\Subtable;
use DB;

class Poll extends Project{

    use Subtable;

    /**
     * METHODS
     * =======
     * public new_poll_insert()
     * public new_pollitem_insert()
     * private validate_huntitem_token()
     * private new_huntitem_qrcode()
     * private insert_huntitem_qrcode()
     */

    /**
    * Insert a new poll into DB
    * @return array to the source
    */
    public function new_poll_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 1 = poll
		$typeid = 1;

        //Calculate new project position
        $position = $this->get_project_position($eventid);

        //DB Table
        $table = "poll";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }

    public function new_pollitem_insert($params){

        $mastertable = "poll";
        $table = "pollitem";

        $insertArray = $this->prepare_new_subtable_insert($mastertable, $table, $params);

		//Insert new subtable entry and return insert id
        try {
    		$pollitemid = DB::table($table)->insertGetId($insertArray);

            if($pollitemid > 0) {

                //Remove server UX time;
                unset($insertArray[$table . '_uxtime']);

                //Prepare API response to client
                $response = $insertArray;

                //Add response values
                $response['status'] = 'success';
                $response[$table . '_id'] = $pollitemid;
                $response[$table . '_text'] = "";
                $response['message'] = 'New ' . $table . ' successfully created';

                return $response;
            }
            else {
                $this->insertFail($table);
                return $response;
            }
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);
			return $errorResponse;
		}
	}

	/** Submit vote  */
	public function submit_vote($pollitemid, $pollid, $eventid, $guestid) {

        try {
            $results = DB::table('pollscore')
            ->where('pollscore_pollid', '=', $pollid)
            ->where('pollscore_guestid', '=', $guestid)
            ->delete();
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);
			return $errorResponse;
		}


        try {
            $uxtime = $this->unixTime();

            //Insert new poll and return intert id
    		$insert = DB::table('pollscore')->insertGetId([
                'pollscore_guestid' => $guestid,
                'pollscore_pollitemid' => $pollitemid,
                'pollscore_pollid' => $pollid,
                'pollscore_eventid' => $eventid,
                'pollscore_uxtime' => $uxtime
            ]);

            $response = array(
                'status' => 'success',
                'message' => 'Your vote has been successfully submited!'
            );

            return  $response;
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);
			return $errorResponse;
		}
	}

    /**
    * @return poll ID from project ID
    */
    public function get_poll_count($pollid, $pollitemid) {

        try {
            $results = DB::table('pollscore')
            ->where('pollscore_pollid' ,'=', $pollid)
            ->where('pollscore_pollitemid' ,'=', $pollitemid)
            ->get();

            $count = count($results);

            return $count;

        }
        //Return error to the client
        catch(Exception $ex) {

            $error = $ex->getMessage();
            $this->writeToLog($error);

            return false;
        }
    }
}
?>
