<?php

namespace App\Classes;
use DB;

class Pindrop extends Project{

    /**
    * Insert a new pindrop into DB
    * @return array to the source
    */
    public function new_pindrop_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 4 = pindrop
		$typeid = 4;

        //Calculate new project position
        $position = $this->get_project_position($eventid);

        //DB Table
        $table = "pindrop";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }
}
