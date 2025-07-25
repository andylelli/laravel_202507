<?php

namespace App\Classes;
use DB;
use Exception;

class Shop extends Project{

    /**
    * Insert a new pindrop into DB
    * @return array to the source
    */
    public function new_shop_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 6 = shop
		$typeid = 6;

        //Calculate new project position
        $position = $this->get_project_position($eventid);

        //DB Table
        $table = "shop";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }

    /**
    * Insert a new shop item into DB
    * @return array to the source
    */
    public function new_shopitem_insert($params){

		$name = $params[0];
		$price = $params[1];
		$projectid = $params[2];
		$eventid = $params[3];

		//Get directory id from project id
		$shopid = $this->get_shopid_by_projectid($projectid);

		//Calculation new shop itemre position in list
		$position = $this->shopitem_position($shopid);

		//Set other variables
		$uxtime = $this->unixTime();

		//Insert new pindrop and return intert id
        try {
    		$shopitemid = DB::table('shopitem')->insertGetId([
    			'shopitem_position' => $position,
    			'shopitem_name' => $name,
    			'shopitem_price' => $price,
    			'shopitem_eventid' => $eventid,
    			'shopitem_shopid' => $shopid,
    			'shopitem_uxtime' => $uxtime
    		]);

			$response = array(
				'status' => 'success',
				'shopitem_id' => $shopitemid,
				'shopitem_position' => $position,
				'shopitem_name' => $name,
				'shopitem_price' => $price,
				'shopitem_text' => null,
				'shopitem_image' => null,
				'shopitem_eventid' => $eventid,
				'shopitem_shopid' => $shopid,
				'message' => 'New shop item added'
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
    * Insert a new shop item into DB
    * @return array to the source
    */
    public function add_order($header_id, $header_eventid, $lineitems){

		//Set other variables
		$uxtime = $this->unixTime();

		//Insert new pindrop and return intert id
        try {
    		$orderid = DB::table('order')->insertGetId([
    			'order_clientid' => $header_id,
    			'order_shopid' => $lineitems[0]['shopid'],
    			'order_eventid' => $header_eventid,
				'order_datetime' => $uxtime,
    			'order_uxtime' => $uxtime
    		]);

    		$i=0;
    		foreach ($lineitems as $lineitem) {

    		    try {
            		$orderdetailid = DB::table('orderdetail')->insertGetId([
            			'orderdetail_shopitemid' => $lineitem['id'],
            			'orderdetail_quantity' => $lineitem['quantity'],
            			'orderdetail_orderid' => $orderid,
            			'orderdetail_eventid' => $header_eventid,
            			'orderdetail_uxtime' => $uxtime,
            		]);
    		    }catch(Exception $ex) {

                    $error = $ex->getMessage();
                    $this->writeToLog($error);

        			$response = array(
        				'status' => 'fail',
        				'message' => 'ERROR: ' . $error
        			);

                    return $response;
    		    }
    		    $lineitems[$i]['orderdetailid'] = $orderdetailid;
    		    $i++;
		    }

        }catch(Exception $ex) {

            $error = $ex->getMessage();


			$response = array(
				'status' => 'fail',
				'message' => 'ERROR: ' . $error
			);

            return $response;
		}

		$response = array(
			'status' => 'success',
			'order_id' => $orderid,
			'order_eventid' => $header_eventid,
			'order_datetime' => $uxtime,
			'lineitems' => $lineitems,
			'message' => 'Order received!'
		);

        return $response;
	}

    /**
    * @return shop ID from project ID
    */
    public function get_shopid_by_projectid($projectid) {

		$results = DB::table('shop')
		->where('shop_projectid' ,'=', $projectid)
		->get();

		$count = count($results);

		if($count > 0) {
			$shopid = $results[0]->shop_id;
		}
		else {
			$shopid = 0;
		}

        return $shopid;
    }

	/**
    * Calculate new shop item position
    * @return $count as position of new shop item in list.
    */
	public function shopitem_position($shopid) {

		$results = DB::table('shopitem')
		->where('shopitem_shopid' ,'=', $shopid)
		->get();

		$count = count($results);

		return  $count;
	}
}
?>
