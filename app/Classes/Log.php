<?php

namespace App\Classes;
use App\Classes\Traits\General;
use DB;
use Illuminate\Http\Request;

class Log{

	use General;

    /**
    * New Event                     	*
    * @param string $userid User ID 	*
    * @return $eventid                  *
    **/
    public function update_log($name, $eventid){
        //Set other variables
        $table = "log";
        $uxtime = $this->unixTime();
    
        // Retrieve the current value from the database
        $currentValue = DB::table($table)
                          ->where($table . '_name', $name)
                          ->where($table . '_eventid', $eventid)
                          ->value($table . '_value');

        // If no value found, set it to 0
        if ($currentValue == false) {
            $currentValue = 0;
        }
    
        // Increment the value by 1
        $newValue = $currentValue + 1;
    
        // Prepare data to insert or update
        $dbInsertArray[$table . '_name'] = $name;
        $dbInsertArray[$table . '_value'] = $newValue;
        $dbInsertArray[$table . '_eventid'] = $eventid;
        $dbInsertArray[$table . '_uxtime'] = $uxtime;
    
        try {
            // Insert or update the log entry
            $logid = DB::table($table)
                        ->updateOrInsert(
                            [$table . '_name' => $name, $table . '_eventid' => $eventid], 
                            $dbInsertArray
                        );
    
            $response = array(
                'status' => 'success',
                'logid' => $logid,
                'message' => 'Log updated with incremented value'
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


}
