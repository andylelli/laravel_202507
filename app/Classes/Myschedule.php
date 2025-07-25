<?php

namespace App\Classes;
use DB;

class Myschedule extends Project{

    /**
    * Insert a new schedule into DB
    * @return array to the source
    */
    public function new_myschedule_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 2 = schedule
		$typeid = 10;

        //Calculate new project position
        $position = $this->get_project_position($eventid);

        //DB Table
        $table = "schedule";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }
}
?>
