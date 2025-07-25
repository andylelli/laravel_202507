<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Traits\General;

class PostDeleteController extends Controller
{
	use General;

    public function postDelete(Request $request, $table)
    {
		$items = $request->all();

		$response[] = array(
			'status' => 'success',
		);

		foreach ($items as $item) {

			$table_id = $table . '_id';
			$table_eventid = $table . '_eventid';

			$id = $item[$table_id];
			$eventid = $item[$table_eventid];

			$id = filter_var($id, FILTER_SANITIZE_ADD_SLASHES);
			$eventid = filter_var($eventid, FILTER_SANITIZE_ADD_SLASHES);

			$results = DB::table($table)
			->where($table . '_id' ,'=', $id)
			->where($table . '_eventid' ,'=', $eventid)
			->get();

			$count = count($results);

			if ($results == false) {

				unset($response);
				$response[] = array(
					'status' => 'fail',
				);
				return response()->json($response, 400);

			}

			if ($count === 0) {

				$response[] = array(
				    'id' => intval($id)
				);
			}
		}

		return response()->json($response, 200);
	}
}
