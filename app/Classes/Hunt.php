<?php

namespace App\Classes;
use App\Classes\Traits\Subtable;
use DB;

/**
 * METHODS
 * =======
 * public new_hunt_insert()
 * public new_huntitem_insert()
 * private validate_huntitem_token()
 * private new_huntitem_qrcode()
 * private insert_huntitem_qrcode()
 */

class Hunt extends Project{

    use Subtable;

    /**
    * Insert a new hunt into DB
    * @return array to the source
    */
    public function new_hunt_insert($params){

		$name = $params[0];
		$eventid = $params[1];

		//ID 7 = hunt
		$typeid = 7;

        //Calculate new project position
        $position = $this->get_project_position($eventid);


        //DB Table
        $table = "hunt";

		//Insert new project and return insert id
		$response = $this->new_project_insert($table, $position, $name, $typeid, $eventid);

        return $response;
    }

    /**
    * Insert a new shop item into DB
    * @return array to the source
    */
    public function new_huntitem_insert($params){

        $mastertable = "hunt";
        $table = "huntitem";

        $insertArray = $this->prepare_new_subtable_insert($mastertable, $table, $params);

		//Insert new subtable entry and return insert id
        try {
    		$huntitemid = DB::table($table)->insertGetId($insertArray);

            if($huntitemid > 0) {

                $user = new _User();
                $token = $user->getToken(16);

                $huntid = $insertArray['huntitem_huntid'];

                $qrcode_value = 'hunt/' . $huntid . '/' . $huntitemid . '/' . $token;
                $qrcode_image = $this->new_huntitem_qrcode($qrcode_value);

                $this->insert_huntitem_qrcode($huntitemid, $qrcode_image, $qrcode_value, $token);

                //Remove server UX time;
                unset($insertArray[$table . '_uxtime']);

                //Prepare API response to client
                $response = $insertArray;

                //Add response values
                $response['status'] = 'success';
                $response[$table . '_id'] = $huntitemid;
                $response[$table . '_text'] = "";
                $response[$table . '_qrcode_image'] = $qrcode_image;
                $response[$table . '_qrcode_value'] = $qrcode_value;
                $response[$table . '_token'] = $token;;
                $response['message'] = 'New ' . $table . ' successfully created';

                return $response;
            }
            else {
                $this->insertFail($table);
                return $response;
            }
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);
			return $errorResponse;
		}
	}

    /**
    * @return hunt ID from project ID
    */
    public function validate_huntitem_token($huntid, $huntitemid, $token) {

		$results = DB::table('huntitem')
		->where('huntitem_id' ,'=', $huntitemid)
		->where('huntitem_huntid' ,'=', $huntid)
		->where('huntitem_token' ,'=', $token)
		->get();

		$count = count($results);

		if($count > 0) {
			return true;
		}
		else {
			return false;
		}
    }

    /**
    * Create new qr code for hunt
    * @return QR image in base64
    */
   private function new_huntitem_qrcode($value){
        //generate a new QR image and save
        $qr = new QR_Code();
        $img = $qr->generate_qr_image_text($value);
        $type = 'png';
        $base64 = 'data:image/' . $type . ';base64,' . $img;

        return $base64;
    }

    /**
     * Insert huntitem QR code
    * @return hunt ID from project ID
    */
    private function insert_huntitem_qrcode($id, $base64, $value, $token) {

		try {
			$result = DB::table('huntitem')
			->where('huntitem_id', $id)
			->update([
				'huntitem_qrcode_image' => $base64,
				'huntitem_qrcode_value' => $value,
				'huntitem_token' => $token,
			]);
		}catch(Exception $ex) {

			$error = $ex->getMessage();

			$response[] = array(
				'message' => 'ERROR: ' . $error
			);

			return response()->json($response, 400);
		}
    }

}
