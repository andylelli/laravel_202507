<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\_User;
use App\Classes\Event;
use App\Classes\Project;
use App\Classes\Poll;
use App\Classes\Scoreboard;
use App\Classes\Pindrop;
use App\Classes\_Directory;
use App\Classes\Shop;
use App\Classes\Hunt;
use App\Classes\News;
use App\Classes\Schedule;
use App\Classes\Myschedule;
use App\Classes\QR_Code;
use App\Classes\Log;
use App\Classes\Traits\General;

class PostInsertController extends Controller
{
	use General;

	public function postInsert(Request $request)
	{
		$hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
		if($hostname == 'LAPTOP-6PCF8EI1') {
			$json = json_decode(file_get_contents(base_path('app\\params.json')));
			$offset = 6;
		}
		else {
			$json = json_decode(file_get_contents(base_path('app/params.json')));
			$offset = 7;
		}

		$params = $json->params;

		$url = $request->url();
		$url_params = explode("/", $url);
		$table = $url_params[$offset];

		$objects = array_filter($params,function($obj) use($table) {
			return $obj->table == $table;
		});

		foreach($objects as $object) {
			$dbInserts = $object->dbInsert;
			$classname = $object->classname;
		}

		$validate=array();

		foreach($dbInserts as $dbInsert) {
			$item = array($dbInsert => 'required');
			$validate = array_merge($validate, $item);
		}

		$this->validate($request, $validate);

		$arr=array();
		foreach($dbInserts as $dbInsert) {
			$param = $request->{$dbInsert};
			array_push($arr, $param);
		}

		$namespace = "App\\Classes\\";
		$type = $namespace . $classname;
		$class = new $type();
		$fxname = 'new_' . $table . '_insert';

		$response = $class->$fxname($arr);

		if($response['status'] == 'success') {

			if($table == "event") {

				$eventid = $response['event_id'];

				$log = new Log();

				$name = "downloads";
				$result_downloads = $log->update_log($name, $eventid);

				$name = "ptr";
				$result_ptr = $log->update_log($name, $eventid);

				if($result_ptr['status'] == 'success') {
					return response()->json($response, 201);
				}
				else {
					return response()->json($response, 400);
				}
			}
			else {
				return response()->json($response, 201);
			}
		}
		else {
			return response()->json($response, 400);
		}

	}

	public function postInsertBulk(Request $request)
	{
        $this->writeToLog("START");

        $post = $request->all();

		$hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
		if($hostname == 'LAPTOP-6PCF8EI1') {
			$json = json_decode(file_get_contents(base_path('app\\params.json')));
			$offset = 7;
		}
		else {
			$json = json_decode(file_get_contents(base_path('app/params.json')));
			$offset = 8; 
		}

		$params = $json->params;

		$url = $request->url();
		$url_params = explode("/", $url);
		$table = $url_params[$offset];

		$objects = array_filter($params,function($obj) use($table) {
			return $obj->table == $table;
		});

		foreach($objects as $object) {
			$dbInsertsBulk = $object->dbInsertBulk;
			$classname = $object->classname;
		} 

        $item = $post[0];

		$bulkArray = json_decode(json_encode($dbInsertsBulk), true);

		$insertArray = array_merge($item, $bulkArray);		

		$namespace = "App\\Classes\\";
		$type = $namespace . $classname;
		$class = new $type();
		$fxname = 'new_' . $table . '_insert_bulk';
		$response = $class->$fxname($insertArray);

        return response()->json($response, 201);
	}

	public function postInsertOrder(Request $request)
	{
	    $order = $request->all();

		$header_id = $order['header']['id'];
        $header_eventid = $order['header']['eventid'];
		$lineitems = $order['lineitems'];

		$header_id = filter_var($header_id, FILTER_SANITIZE_ADD_SLASHES);
		$header_eventid = filter_var($header_eventid, FILTER_SANITIZE_ADD_SLASHES);

		foreach ($lineitems as $lineitem) {

		    $lineitem_shopitemid = $lineitem['id'];
		    $lineitem_name = $lineitem['name'];
		    $lineitem_text = $lineitem['text'];
		    $lineitem_price = $lineitem['price'];
		    $lineitem_quantity = $lineitem['quantity'];
		    $lineitem_shopid = $lineitem['shopid'];

		    $lineitem_shopitemid = filter_var($lineitem_shopitemid, FILTER_SANITIZE_ADD_SLASHES);
		    $lineitem_name = filter_var($lineitem_name, FILTER_SANITIZE_ADD_SLASHES);
		    $lineitem_text = filter_var($lineitem_text, FILTER_SANITIZE_ADD_SLASHES);
		    $lineitem_price = filter_var($lineitem_price, FILTER_SANITIZE_ADD_SLASHES);
		    $lineitem_quantity = filter_var($lineitem_quantity, FILTER_SANITIZE_ADD_SLASHES);
		    $lineitem_shopid = filter_var($lineitem_shopid, FILTER_SANITIZE_ADD_SLASHES);

		}

		$shop = new Shop();

		// --> SEND PAYMENT TO APPLE / ANDROID
		$response = $shop->add_order($header_id, $header_eventid, $lineitems);

		if($response['status'] == 'success') {
            return response()->json($response, 201);
		}
		else {
			return response()->json($response);
		}
	}
}
