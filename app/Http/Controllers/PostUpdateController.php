<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Traits\General;
use Exception;

class PostUpdateController extends Controller
{
	use General;


	public function postUpdate(Request $request)
	{
		$updates = $request->all();

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
			$dbUpdates = $object->dbUpdate;
		}

		$c=0;
		$e=0;
		foreach ($updates as $update) {

			foreach ($update as $key => $value) {
				$exists = array_search($key, $dbUpdates);

				if (false !== $exists) {
					//Do nothing
				}
				else {
					unset($update[$key]);
				}
				if ($key == $table . '_id') {
					$id = $value;
					unset($update[$key]);
				}
			}

			$update = filter_var_array($update, FILTER_SANITIZE_ADD_SLASHES);

			$uxtime = $this->unixTime();
			$key = $table . '_uxtime';
			$item = array($key => $uxtime);
			$update = array_merge($update, $item);

			$keys = array_keys($update);
	
			$keyToChange = $table . '_name';;
			if (in_array($keyToChange, $keys)) {
				$update[$keyToChange] = $this->encodeXML($update[$keyToChange]);
			}

			$keyToChange = $table . '_text';;
			if (in_array($keyToChange, $keys)) {
				$update[$keyToChange] = $this->encodeXML($update[$keyToChange]);
			}				

			$keyToChange = $table . '_shorttext';;
			if (in_array($keyToChange, $keys)) {
				$update[$keyToChange] = $this->encodeXML($update[$keyToChange]);
			}		
			
			$keyToChange = $table . '_longtext';;
			if (in_array($keyToChange, $keys)) {
				$update[$keyToChange] = $this->encodeXML($update[$keyToChange]);
			}	

			$keyToChange = $table . '_title';;
			if (in_array($keyToChange, $keys)) {
				$update[$keyToChange] = $this->encodeXML($update[$keyToChange]);
			}	

			$keyToChange = $table . '_detail';;
			if (in_array($keyToChange, $keys)) {
				$update[$keyToChange] = $this->encodeXML($update[$keyToChange]);
			}	

			$keyToChange = $table . '_expiredmessage';;
			if (in_array($keyToChange, $keys)) {
				$update[$keyToChange] = $this->encodeXML($update[$keyToChange]);
			}
			
			// Update Guest table when event changes
			if($table == "event") {
				$eventName = $update['event_name'];
				$this->postGuestEmailUpdate($id, $eventName);
			}
		

            try {
				$tableid = $table . '_id';
    			$result = DB::table($table)
                ->where($tableid, $id)
                ->update($update);

            }catch(Exception $ex) {
                $error = $ex->getMessage();
    			$response[] = array(
    				'status' => 'fail',
    				'count' => $c,
    				'errors' => $e,
    				'message' => 'ERROR: ' . $error
    			);
				$this->writeToLog('COUNT: ' . $c);
				$this->writeToLog('ERRORS: ' . $e);
				$this->writeToLog('ERROR MESSAGE: ' . $error);
                return response()->json($response, 400);
            }

			if($result > 0 ) {
				$c++;
			}
			else{
				$e++;
			}
		}

		$array = $this->returnResponse($c, $e);

		return response()->json($array[0]['response'], $array[0]['httpcode']);
	}

	private function postGuestEmailUpdate($eventid, $eventName) {

		// Convert event name to lowercase and replace spaces with hyphens
		$formattedEventName = strtolower(str_replace(' ', '-', $eventName));
	
		// Update the email with the formatted event name
		$email = $formattedEventName . '@evaria.io';
	
		$uxtime = $this->unixTime();
	
		$update = array(
			'guest_firstname' => $eventName,
			'guest_email' => $email,
			'guest_uxtime' => $uxtime
		);
	
		$result = DB::table('guest')
			->where('guest_eventid', $eventid)
			->where('guest_role', 1)
			->update($update);
	
		return $result;
	}
	

	public function postUpdateLookup(Request $request)
	{
		$updates = $request->all();

		$c=0;
		$e=0;
		foreach ($updates as $update) {

			$id = $update['lookup_id'];
			$value = $update['lookup_value'];
			$eventid = $update['lookup_eventid'];

			$id = filter_var($id, FILTER_SANITIZE_ADD_SLASHES);
			$value = filter_var($value, FILTER_SANITIZE_ADD_SLASHES);
			$eventid = filter_var($eventid, FILTER_SANITIZE_ADD_SLASHES);

			$uxtime = $this->unixTime();

            try {

    			$result = DB::table('lookup')
    			->where('lookup_id', $id)
                ->where('lookup_eventid', $eventid)
                ->update([
                    'lookup_value' => $value,
    				'lookup_uxtime' => $uxtime,
    			]);
            }catch(Exception $ex) {

                $error = $ex->getMessage();

    			$response[] = array(
    				'status' => 'fail',
    				'count' => $c,
    				'errors' => $e,
    				'message' => 'ERROR: ' . $error
    			);

                return response()->json($response, 400);
            }

			if($result > 0 ) {
				$c++;
			}
			else{
				$e++;
			}
		}

		$array = $this->returnResponse($c, $e);

		return response()->json($array[0]['response'], $array[0]['httpcode']);
	}

	private function returnResponse($c, $e) {

		if($c==0 && $e>0) {
			$response[] = array(
				'status' => 'fail',
				'count' => $c,
				'errors' => $e,
				'message' => 'Update failed'
			);
			$httpcode = 400;
		}
		elseif($c>0 && $e>0) {
			$response[] = array(
				'status' => 'partial success',
				'count' => $c,
				'errors' => $e,
				'message' => 'Partial success'
			);
			$httpcode = 206;
		}
		else {
			$response[] = array(
				'status' => 'success',
				'count' => $c,
				'errors' => $e,
				'message' => 'Update success'
			);
			$httpcode = 201;
		}

		$array[] = array(
			'response' => $response,
			'httpcode' => $httpcode
		);

		return ($array);
	}

}
