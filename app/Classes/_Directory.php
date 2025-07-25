<?php

namespace App\Classes;
use App\Classes\Traits\Subtable;
use DB;

/**
 * METHODS
 * =======
 * public new_directory_insert()
 * public new_directoryentry_insert()
 * private remove_hidden_directoryentry_names()
 * private get_hidden_directories_by_eventid()
 */

class _Directory extends Project{

    use Subtable;

    /**
    * Insert a new directory into DB
    * @return array to the source
    */
    public function new_directory_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 5 = directory
		$typeid = 5;

        //Calculate new project position
        $position = $this->get_project_position($eventid);

        //DB Table
        $table = "directory";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }

    /**
    * Insert a new directory entry into DB
    * @return array to the source
    */
    public function new_directoryentry_insert($params){

        $mastertable = "directory";
        $table = "directoryentry";

        $insertArray = $this->prepare_new_subtable_insert($mastertable, $table, $params);

		//Insert new subtable entry and return insert id
        try {
            $directoryentryid = DB::table($table)->insertGetId($insertArray);

            //If insert successfully returned an insert id
            if($directoryentryid > 0) {

                //Remove server UX time;
                unset($insertArray[$table . '_uxtime']);

                //Prepare API response to client
                $response = $insertArray;

                //Add response values
                $response['status'] = 'success';
                $response[$table . '_id'] = $directoryentryid;
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

        /**
    * Insert a new directory entry into DB
    * @return array to the source
    */
    public function new_directoryentry_insert_bulk($insertArray){

        $mastertable = "directory";
        $table = "directoryentry";
        $mastertableid = $insertArray['directoryentry_directoryid'];

		//Calculation new directory entry position in list
		$position = $this->get_subtable_position($table, $mastertable, $mastertableid);

		//Set other variables
		$uxtime = $this->unixTime();

        $insertArray['directoryentry_position'] = $position;
        $insertArray['directoryentry_uxtime'] = $uxtime;

        //$this->writeToLog(print_r($insertArray, true));

		//Insert new subtable entry and return insert id
        try {
            $directoryentryid = DB::table($table)->insertGetId($insertArray);

            //If insert successfully returned an insert id
            if($directoryentryid > 0) {

                //Remove server UX time;
                unset($insertArray[$table . '_uxtime']);

                //Prepare API response to client
                $response = $insertArray;

                //Add response values
                $response['status'] = 'success';
                $response[$table . '_id'] = $directoryentryid;
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
            $this->writeToLog(print_r($ex, true));

            $errorResponse = $this->errorException($ex);
			return $errorResponse;
		}
	}

        /**
    * Remove directory entries with a hidden schedule
    * @return array to the source
    */
    public function remove_hidden_directoryentry_names($results, $eventid){

        $hiddendirectories = $this->get_hidden_directories_by_eventid($eventid);

        foreach ($hiddendirectories as $directory) {

			$hidenames = $directory->directory_hidenames;
            $hidetype = $directory->directory_hidetype;
            $directoryid = $directory->directory_id;
            $len = count($results);
            $i=0;

            if($hidenames == 1 && $hidetype == 0) {
                for ($i = 0; $i < $len; $i++) {
                    if($results[$i]->directoryentry_directoryid == $directoryid) {
                        $results[$i]->directoryentry_name = "TBC";
                    }
                }
            }
            elseif($hidenames == 1 && $hidetype == 1) {
                for ($i = 0; $i < $len; $i++) {
                    if($results[$i]->directoryentry_directoryid == $directoryid && $results[$i]->directoryentry_schedulehide == 1) {
                        $results[$i]->directoryentry_name = "TBC";
                    }
                }
            }
        }

        return $results;
    }

    /**
    * @return directories with hidden schedule information by event_id
    */
    public function get_hidden_directories_by_eventid($eventid) {

		$results = DB::table('directory')
		->where('directory_eventid' ,'=', $eventid)
        ->where('directory_hidenames' ,'=', 1)
		->get();

        return $results;
    }
}
?>
