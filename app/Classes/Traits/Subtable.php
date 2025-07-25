<?php

namespace App\Classes\Traits;
use DB;

trait Subtable{
    /** @var object $template */
    public $temp1;
    /** @var object $template */
    private $temp2;

    /**
    * Prepare data for a new subtable insert
    * @return array to the requestor
    */
    public function prepare_new_subtable_insert($mastertable, $subtable, $params){

		$name = $params[0];
		$projectid = $params[1];
		$eventid = $params[2];

		//Get directory id from project id
		$mastertableid = $this->get_mastertableid_by_projectid($mastertable, $projectid);

		//Calculation new directory entry position in list
		$position = $this->get_subtable_position($subtable, $mastertable, $mastertableid);

		//Set other variables
		$uxtime = $this->unixTime();

        //Get JSON parameters
        $property = "dbInsertInitial";
        $dbInsertInitial = $this->getJSONParams($subtable, $property);

        //Convert from Object to Array
        $dbInsertArray = json_decode(json_encode($dbInsertInitial), true);

        $key = $subtable . '_' . $mastertable . 'id';

        //Add mandatory values
        $dbInsertArray[$subtable . '_name'] = $name;
        $dbInsertArray[$subtable . '_position'] = $position;
        $dbInsertArray[$key] = $mastertableid;
        $dbInsertArray[$subtable . '_eventid'] = $eventid;
        $dbInsertArray[$subtable . '_uxtime'] = $uxtime;

        return $dbInsertArray;
    }

	/**
    * Calculate new subtable entry position
    * @return $count as position of new subtable entry in list.
    */
	public function get_subtable_position($subtable, $mastertable, $mastertableid) {

		$results = DB::table($subtable)
		->where($subtable . "_" . $mastertable . "id" ,'=', $mastertableid)
		->get();

		$count = count($results);

		return  $count;
	}

    /**
    * Get mastertable id from project id*
    * @return mastertable id
    */
    public function get_mastertableid_by_projectid($mastertable, $projectid) {

		$results = DB::table($mastertable)
		->where($mastertable . '_projectid' ,'=', $projectid)
		->get();

		$count = count($results);

		if($count > 0) {
			$masterid = $results[0]->{$mastertable . "_id"};
		}
		else {
			$masterid = 0;
		}

        return $masterid;
    }

}
?>
