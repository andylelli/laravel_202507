<?php

namespace App\Classes\Traits;
use DB;

trait General{
    /** @var object $pdo Copy of PDO connection */
    public $pdo;
    /** @var object of the Db connect trait*/
    private $db;
    /** @var string error msg */
    public $msg;


    /**
    * Print error msg function
    * @return void.
    */
    public function printMsg(){
        return $this->msg;
    }

    /**
    * Add new lookup value                                *
    * @param string lookup id as $lookup_id               *
    * @param string lookup value as $lookup_value         *
    * @param string lookup eventid as $lookup_eventid     *
    * @return true.                                       *
    */
    public function new_lookup_insert($params){

        $id =  $params[0];
        $value = $params[1];
        $eventid = $params[2];

        $uxtime = $this->unixTime();

		DB::table('lookup')
		->where('lookup_id', '=', $id)
		->where('lookup_eventid', '=', $eventid)
		->delete();

		//Insert project
		$results = DB::table('lookup')->insert([
			'lookup_id' => $id,
			'lookup_value' => $value,
			'lookup_eventid' => $eventid,
			'lookup_uxtime' => $uxtime
		]);

		if($results == true) {
			$response= array(
				'status' => 'success',
				'message' => 'New lookup created'
			);

			return $response;
		}
		else{
			$response = array(
				'status' => 'fail',
				'message' => 'Error creating new lookup'
			);
			return $response;
		}
    }

    /**
    * Turn image into base64 string
    * @return base64 string.
    */
    public function generalBase64($path) {

        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        return $base64;
    }

    public function getJSONParams($table, $property) {
        //Get JSON parameters
        $json = json_decode(file_get_contents(base_path('app/params.json')));
        $params = $json->params;

        $objects = array_filter($params,function($obj) use($table) {
            return $obj->table == $table;
        });

        //Get empty insert values
        foreach($objects as $object) {
            $data = $object->$property;
        }

        return $data;
    }

    public function getJSONParamsAll($param) {

        $file = json_decode(file_get_contents(base_path('app/params.json')));
        $params = $file->params;
        
        foreach ($params as $object) {
            if (property_exists($object, $param)) {
                $value = $object->$param;
                $filteredArray[] = $value;
            }
        }

        return $filteredArray;

    }    

    public function insertFail($tablen) {

        $response = array(
            'status' => 'fail',
            'message' => 'Error creating new ' . $table
        );

        return $response;
    }

    public function errorException($exception) {

        $error = $exception->getMessage();
        $this->writeToLog($error);

        $response = array(
            'status' => 'fail',
            'message' => $error
        );

        return $response;
    }

    public function unixTime() {

        $uxtime = time() + 60;

        return $uxtime;
    }

    public function writeToLog($log_entry){

        $myFile = "log/log.txt";

        $log_file = file_get_contents($myFile);

    	$log = date("d/m/y H:i:s - ", time()) . $log_entry . "\r";
    	$log_all = $log_file . $log . "\n";

    	$fh = fopen($myFile, 'w') or die("can't open file");
    	fwrite($fh, $log_all);
    	fclose($fh);
    	$log_entry = '';
    }

    public function writeToErrorLog($log_entry){

        $myFile = "log/error.log";

        $log_file = file_get_contents($myFile);

    	$log = date("d/m/y H:i:s - ", time()) . $log_entry . "\r";
    	$log_all = $log_file . $log . "\n";

    	$fh = fopen($myFile, 'w') or die("can't open file");
    	fwrite($fh, $log_all);
    	fclose($fh);
    	$log_entry = '';
    }     
    
    public function encodeXML($string){

        $string = str_replace("&", "&amp;", $string);
        $string = str_replace('"', "&quot;", $string);
        $string = str_replace("'", "&apos;", $string);
        $string = str_replace("<", "&lt;", $string);
        $string = str_replace(">", "&gt;", $string);
        $string = str_replace("\\", "", $string);
        $string = str_replace(",", "&com;", $string);
        
        // Add replacements for accented characters
        $string = str_replace("á", "&aacute;", $string);
        $string = str_replace("Á", "&Aacute;", $string);
        $string = str_replace("à", "&agrave;", $string);
        $string = str_replace("À", "&Agrave;", $string);
        $string = str_replace("â", "&acirc;", $string);
        $string = str_replace("Â", "&Acirc;", $string);
        $string = str_replace("ä", "&auml;", $string);
        $string = str_replace("Ä", "&Auml;", $string);
        $string = str_replace("ã", "&atilde;", $string);
        $string = str_replace("Ã", "&Atilde;", $string);
        $string = str_replace("å", "&aring;", $string);
        $string = str_replace("Å", "&Aring;", $string);
        $string = str_replace("æ", "&aelig;", $string);
        $string = str_replace("Æ", "&AElig;", $string);
        $string = str_replace("ç", "&ccedil;", $string);
        $string = str_replace("Ç", "&Ccedil;", $string);
        $string = str_replace("é", "&eacute;", $string);
        $string = str_replace("É", "&Eacute;", $string);
        $string = str_replace("è", "&egrave;", $string);
        $string = str_replace("È", "&Egrave;", $string);
        $string = str_replace("ê", "&ecirc;", $string);
        $string = str_replace("Ê", "&Ecirc;", $string);
        $string = str_replace("ë", "&euml;", $string);
        $string = str_replace("Ë", "&Euml;", $string);
        $string = str_replace("í", "&iacute;", $string);
        $string = str_replace("Í", "&Iacute;", $string);
        $string = str_replace("ì", "&igrave;", $string);
        $string = str_replace("Ì", "&Igrave;", $string);
        $string = str_replace("î", "&icirc;", $string);
        $string = str_replace("Î", "&Icirc;", $string);
        $string = str_replace("ï", "&iuml;", $string);
        $string = str_replace("Ï", "&Iuml;", $string);
        $string = str_replace("ñ", "&ntilde;", $string);
        $string = str_replace("Ñ", "&Ntilde;", $string);
        $string = str_replace("ó", "&oacute;", $string);
        $string = str_replace("Ó", "&Oacute;", $string);
        $string = str_replace("ò", "&ograve;", $string);
        $string = str_replace("Ò", "&Ograve;", $string);
        $string = str_replace("ô", "&ocirc;", $string);
        $string = str_replace("Ô", "&Ocirc;", $string);
        $string = str_replace("ö", "&ouml;", $string);
        $string = str_replace("Ö", "&Ouml;", $string);
        $string = str_replace("õ", "&otilde;", $string);
        $string = str_replace("Õ", "&Otilde;", $string);
        $string = str_replace("ø", "&oslash;", $string);
        $string = str_replace("Ø", "&Oslash;", $string);
        $string = str_replace("œ", "&oelig;", $string);
        $string = str_replace("Œ", "&OElig;", $string);
        $string = str_replace("ß", "&szlig;", $string);
        $string = str_replace("ú", "&uacute;", $string);
        $string = str_replace("Ú", "&Uacute;", $string);
        $string = str_replace("ù", "&ugrave;", $string);
        $string = str_replace("Ù", "&Ugrave;", $string);
        $string = str_replace("û", "&ucirc;", $string);
        $string = str_replace("Û", "&Ucirc;", $string);
        $string = str_replace("ü", "&uuml;", $string);
        $string = str_replace("Ü", "&Uuml;", $string);
                                                   

        return $string;
    }          
}
?>
