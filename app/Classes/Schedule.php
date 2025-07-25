<?php

namespace App\Classes;
use DB;

class Schedule extends Project{

    /**
    * Insert a new schedule into DB
    * @return array to the source
    */
    public function new_schedule_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 2 = schedule
		$typeid = 9;

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
