<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Traits\General;

class DeleteController extends Controller
{
	use General;

    public function deleteItem($table, $id)
    {
		$results = DB::table($table)
		->where($table . '_id', '=', $id)
		->delete();

		if($results == true) {

			$response[] = array(
				'status' => 'success',
				'message' => $table . ' deleted'
			);

			return response()->json($response, 200);
		}
		else {

			$response[] = array(
				'status' => 'fail',
				'message' => 'Error deleting ' . $table
			);

			return response()->json($response, 400);
		}
	}
    public function deleteAll($table, $parenttable, $id)
    {

        $results = DB::table($table)
		->where($table . '_' . $parenttable . 'id', '=', $id)
        ->get();

        if(count($results) == 0) {
			$response[] = array(
				'status' => 'success',
                'count' => 0,
				'message' => 'There are no ' . $table . ' records to delete'
			);

			return response()->json($response, 200);

        }

		$results = DB::table($table)
		->where($table . '_' . $parenttable . 'id', '=', $id)
		->delete();

		if($results == true) {
			$response[] = array(
				'status' => 'success',
				'message' => $table . ' deleted'
			);

			return response()->json($response, 200);
		}
		else {
			$response[] = array(
				'status' => 'fail',
				'message' => 'Error deleting ' . $table
			);

			return response()->json($response, 400);
		}
	}
}
