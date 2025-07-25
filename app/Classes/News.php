<?php

namespace App\Classes;
use DB;

class News extends Project{

    /**
    * Insert a new news into DB
    * @return array to the source
    */
    public function new_news_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 8 = news
		$typeid = 8;

        //Calculate new project position
        $position = $this->get_project_position($eventid);

        //DB Table
        $table = "news";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }

    /**
    * Insert a new shop item into DB
    * @return array to the source
    */
    public function new_newsitem_insert($params){

		$title = $params[0];
        $detail = $params[1];
		$directoryentryid = $params[2];
        $userid = $params[3];
		$projectid = $params[4];
        $eventid = $params[5];

		//Get news id from project id
		$newsid = $this->get_newsid_by_projectid($projectid);

		//Set other variables
		$uxtime = $this->unixTime();

		//Insert new news and return intert id
        try {
    		$newsitemid = DB::table('newsitem')->insertGetId([
    			'newsitem_title' => $title,
                'newsitem_detail' => $detail,
    			'newsitem_projectid' => $projectid,
                'newsitem_directoryentryid' => $directoryentryid,
                'newsitem_time' => $uxtime,
                'newsitem_userid' => $userid,
    			'newsitem_newsid' => $newsid,
    			'newsitem_eventid' => $eventid,
    			'newsitem_uxtime' => $uxtime
    		]);


			$response = array(
				'status' => 'success',
				'newsitem_id' => $newsitemid,
    			'newsitem_title' => $title,
                'newsitem_detail' => $detail,
    			'newsitem_projectid' => $projectid,
                'newsitem_directoryentryid' => $directoryentryid,
                'newsitem_time' => $uxtime,
                'newsitem_userid' => $userid,
    			'newsitem_newsid' => $newsid,
    			'newsitem_eventid' => $eventid,
				'message' => 'New news item added'
			);

			return $response;
        }
        //Return error to the client
        catch(Exception $ex) {

            $error = $ex->getMessage();
            $this->writeToLog($error);

			$response = array(
				'status' => 'fail',
				'message' => $error
			);

			return $response;
		}
	}

    /**
    * @return news ID from project ID
    */
    public function get_newsid_by_projectid($projectid) {

		$results = DB::table('news')
		->where('news_projectid' ,'=', $projectid)
		->get();

		$count = count($results);

		if($count > 0) {
			$newsid = $results[0]->news_id;
		}
		else {
			$newsid = 0;
		}

        return $newsid;
    }

}
