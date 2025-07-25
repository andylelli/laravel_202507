<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Traits\General;

class PostEventBackup extends Controller
{
    use General;

    private $path = "backup/";

    public function postRestoreEventBackup(Request $request) {

        $this->writeToLog(print_r("WRITE TO LOG TEST", true)); 

        $data = $request->all();
        $header = $request->header();

        $eventid = $header['eventid'][0];
        $file = $data['file'];

        $filename = $this->path . "backup_" . $eventid . "_" . $file . ".txt";

        $response = $this->deleteExistingEventData($eventid);

        if($response['status'] == "fail") {
            return response()->json($response, 400);
        }        
        else {
            $response = $this->restoreFromFile($filename, $eventid);
        }

        if($response['status'] == "success") {
            return response()->json($response, 200);
        }
        else {
            return response()->json($response, 400);
        } 
    }

    private function deleteExistingEventData($eventid) {

        $tables = $this->getJSONParamsAll("table");

        foreach ($tables as $table) { 

            if($table != "static_data" && $table != "user") {

                try {

                    if($table == "event") {
                        DB::table('event')
                        ->where('event_id', $eventid)
                        ->delete();
                    }
                    else {
                        $column = $table . "_eventid";
                        DB::table($table)
                        ->where($column, $eventid)
                        ->delete();
                    }

                } 
                catch (Exception $e) {
                    $str = "Error deleting rows from table '$table': " . $e->getMessage() . "\n";
                    $response = array(
                        'status' => 'fail',
                        'message' => $str
                    );

                    return $response;
                }  
          
            }
        } 

        $response = array(
            'status' => 'success',
            'message' => 'Deletion of event completed successfully!'
        );

        return $response;


    }

    public function restoreFromFile($filename, $eventid) {

        // Read the contents of the file
        $fileContents = file_get_contents($filename);

        // Remove empty lines from the file contents
        $fileContents = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $fileContents);

        // Split the file contents into individual statements
        $statements = explode(';@@@END@@@', $fileContents);

        // Remove empty statements
        $statements = array_filter($statements, function ($statement) {
            return !empty(trim($statement));
        });

        // Execute each statement separately
        foreach ($statements as $statement) {
            // Execute the SQL statement using Laravel's DB facade or Eloquent ORM
            try {
                DB::statement($statement);

            } catch (Exception $e) {
                $str = 'Error executing statement: ' . $statement . PHP_EOL;
       
                $str = 'Error message: ' . $e->getMessage() . PHP_EOL;

                $response = array(
                    'status' => 'fail',
                    'message' => 'Restoration of backup failed'
                );

                return response()->json($response, 400);
            }
        } 

        // Create the full path to the file
        $globString = $this->path . "backup_" . $eventid . "_*.txt";

        // Create string of backup filenames
        $existingFilesArr = glob($globString);
        $existingFilesString = implode('|', $existingFilesArr);  

        $lookup_value = $this->strip_string($existingFilesString, $eventid);
        $lookup_value = str_replace("backup/", "", $lookup_value); 

        $uxtime = $this->unixTime();

        // Insert new lookup entry
        $result = DB::table('lookup')->insertGetId([
            'lookup_id' => "backup-files",
            'lookup_value' => $lookup_value,
            'lookup_eventid' => $eventid,
            'lookup_uxtime' => $uxtime,
        ]);
        
        $response = array(
            'status' => 'success',
            'message' => 'Restoration completed successfully!'
        );

        return $response;
    }  
    
    private function strip_string($string, $eventid) {

        $strip1 = "backup_" . $eventid . "_";
        $strip2 = ".txt";

        $string = str_replace($strip1, "", $string);
        $string = str_replace($strip2, "", $string);

        return $string;
    }    
}
