<?php

namespace App\Classes;
use App\Classes\Traits\General;
use DB;

class Project{

	use General;

    /**
     * METHODS
     * =======
     * public new_project_insert()
     * private insert_project()
     * private insert_subtable()
     * public get_project_position()
     * private new_project_code()
     */

    /**
    * New project insert
    * @return $projectid as project insert id
    */
	public function new_project_insert($table, $position, $name, $typeid, $eventid) {

        //Generate other values
		$code = $this->new_project_code();
        $uxtime = $this->unixTime();

        $projectResponse = $this->insert_project($table, $position, $name, $typeid, $eventid, $code, $uxtime);
        $projectid = $projectResponse['project_id'];
        $subtableResponse = $this->insert_subtable($table, $projectid, $eventid, $uxtime);

        $response = array_merge($projectResponse, $subtableResponse);

        //Remove server UX time;
        unset($response[$table . '_uxtime']);

        //Add response values
        $response['status'] = 'success';
        $response['message'] = 'New ' . $table . ' successfully created';

        return $response;

    }

    /**
    * New project insert
    * @return $projectid as project insert id
    */
	private function insert_project($table, $position, $name, $typeid, $eventid, $code, $uxtime) {

        //PREPARE DYNAMIC INITIAL VALUES

        //Get JSON parameters
        $property = "dbInsert";
        $insertKeys = $this->getJSONParams('project', $property);

        $dynamicValuesArr = [];
        $len = count($insertKeys);

        for ($i = 0; $i < $len; $i++) {
            $keyName = 'project_' . $insertKeys[$i];
            $var = $insertKeys[$i];
            $dynamicValuesArr[$keyName] = $$var;
        }

        //PREPARE PRE DEFINED INITIAL VALUES

        //Get JSON parameters
        $property = "dbInsertInitial";
        $insertInitialKeys = $this->getJSONParams('project', $property);

        //Convert from Object to Array
        $staticValuesArr = json_decode(json_encode($insertInitialKeys), true);

        //MERGE THE DYNAMIC AND STATIC ARRAYS
        $insertArray = array_merge($dynamicValuesArr, $staticValuesArr);

		try {
            //Insert project sand return values as array
            $projectid = DB::table('project')->insertGetId($insertArray);

            $insertArray['project_id'] = $projectid;

            return $insertArray;
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);
			return $errorResponse;
		}

    }

    /**
    * Insert new entry into subtable
    * @return $projectid as project insert id
    */
	private function insert_subtable($table, $projectid, $eventid, $uxtime) {

        //INSERT INTO SUBTABLE

        //Get JSON parameters
        $property = "dbInsertInitial";
        $dbInsertInitial = $this->getJSONParams($table, $property);

        //Convert from Object to Array
        $insertArray = json_decode(json_encode($dbInsertInitial), true);

        //Add mandatory values
        $insertArray[$table . '_projectid'] = $projectid;
        $insertArray[$table . '_eventid'] = $eventid;
        $insertArray[$table . '_uxtime'] = $uxtime;

		try {
            //Insert new subtable entry and return values as array
            $subtableid = DB::table($table)->insertGetId($insertArray);

            $insertArray[$table . '_id'] = $subtableid;

            return $insertArray;
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);
			return $errorResponse;
		}
    }

    /**
    * Calculate new project position
    * @return $count as position of new project in list.
    */
	public function get_project_position($eventid) {

		$results = DB::table('project')
		->where('project_eventid' ,'=', $eventid)
		->get();

		$count = count($results);

		return  $count;
	}

    /**
    * Create a new project code and confirm it is unique
    * @return code as new project code.
    */
    private function new_project_code(){
        do {
            $characters = 'abcdefghijklmnopqrstuvwxyz';
            $code = '';
            $max = 25;
            for ($i = 0; $i < 6; $i++) {
              $code .= $characters[mt_rand(0, $max)];
            }

			$results = DB::table('project')
			->select('project_code')
			->where('project_code' ,'=', $code)
			->get();

			$count = count($results);

            if ($count == 0) {
                return $code;
                break;

            }
        } while ($count > 0);
	}
}
?>
