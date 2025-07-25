<?php

namespace App\Classes;
use DB;

class Scoreboard extends Project{

    /**
    * Insert a new scoreboard into DB
    * @return array to the source
    */
    public function new_scoreboard_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 2 = scoreboard
		$typeid = 2;

        //Calculate new project position
        $position = $this->get_project_position($eventid);

        //DB Table
        $table = "scoreboard";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }
}
?>
