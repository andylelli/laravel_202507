<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Traits\General;

class GetEventBackup extends Controller
{
    use General;

    private $path = "backup/";

    public function getRestoreEventBackup($eventid, $file)
	{
        $request = $this->request();

        // Save sql to file
        $response = array(
            'status' => 'success'
        );
    
        //Send response
        if($response['status'] == "success") {
            return response()->json($response, 200);
        }
        else {
            return response()->json($response, 400);
        }    

    }    

    public function getInsertEventBackup($eventid, $file)
	{
        //Save sql to file
        $sql = $this->generate_sql($eventid);

        //If error, send response
        if($sql['status'] == "fail") {
            return response()->json($sql, 400);
        }      

        // Do housekeeping
        if($file != 0) {
            $housekeeping = $this->housekeeping($file, $eventid);

            //If error, send response
            if($housekeeping['status'] == "fail") {
                return response()->json($housekeeping, 400);
            } 
        }  

        // Save sql to file
        $response = $this->saveToFile($sql['sql'], $eventid);
    
        //Send response
        if($response['status'] == "success") {
            return response()->json($response, 200);
        }
        else {
            return response()->json($response, 400);
        }    

    }

    private function generate_sql($eventid) {
      
        try {

            // Get parameters file
            $json = json_decode(file_get_contents(base_path('app/params.json')));
            $objects = $json->params;
            $inserts = [];

            foreach ($objects as $object) { 

                $table = $object->table;

                if($table != "static_data" && $table != "user") {
                    $columns = $object->db; 
                    $insert = array(
                        'table' => $table,
                        'columns' => $columns
                    ); 
                    array_push($inserts, $insert);            
                }
            }  
            
            $sql = "";            

            foreach ($inserts as $insert) { 
            
                // Table
                $table = $insert['table'];
                $columns = $insert['columns'];

                // Your database condition
                if($table == 'event') {
                    $condition = $table . '_id = ' . $eventid;
                }
                elseif($table == 'lookup') {
                    $condition = 'lookup_id <> "backup-files" AND lookup_eventid = ' . $eventid; 
                }
                else {
                    $condition = $table . '_eventid = ' . $eventid; 
                }

                // Prepare the SELECT query
                $query = "SELECT " . implode(', ', $columns) . " FROM `$table` WHERE $condition";

                // Execute the SELECT query
                $results = DB::select($query);

                if($results) {

                    // Iterate through the rows and generate insert statements
                    foreach ($results as $row) {
                        $insertValues = array();
                        foreach ($columns as $column) {
                            $insertValues[] = "'" . addslashes($row->$column) . "'";
                        }
                        $insertStatement = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $insertValues) . ");";
                        $sql .=  $insertStatement . "@@@END@@@" . PHP_EOL;
                    }   
                }
            } 
            
            $response = array(
                'status' => 'success',
                'sql' => $sql
            );
            
            return $response;
        }
        //Send error response
        catch(Exception $ex) {
            $response = array(
                'status' => 'fail',
                'message' => 'There was an error creating backup file'
            );
            return $response;
        }        
    }

    private function housekeeping($file, $eventid) {

        try {

            // Create the full path to the file
            $filename = $this->path . "backup_" . $eventid . "_" . $file . ".txt";

            // Delete the oldest file                
            if (unlink($filename)) {
                $response = array(
                    'status' => 'success'
                );
                return $response;   
            }
        }
        catch(Exception $ex) {
            //Send error response
            $response = array(
                'status' => 'fail',
                'message' => 'There was an error creating backup file'
            );
            return $response;   
        }        

    }

    private function saveToFile($sql, $eventid)
	{    
        try {

            // Maximum number of files to keep
            $maxFiles = 5;

            // Generate the filename with the variable and date stamp
            $filename = $this->path . "backup_" . $eventid . "_" . date("Ymd_His") . ".txt";

            //Open file
            $filehandle = fopen($filename, "w");

            // Write to file
            fwrite($filehandle, $sql);

            //Close file
            fclose($filehandle);

            $response = $this->lookupDB($eventid);

            return $response;

        }
        catch(Exception $ex) {
            //Send error response
            $response = array(
                'status' => 'fail',
                'message' => 'There was an error creating backup file'
            );
            return $response;   
        }
	}
    private function lookupDB($eventid) {

        try {

            // Create the full path to the file
            $globString = $this->path . "backup_" . $eventid . "_*.txt";

            // Create string of backup filenames
            $existingFilesArr = glob($globString);
            $existingFilesString = implode('|', $existingFilesArr); 
            $existingFilesString = str_replace("backup/", "", $existingFilesString);        

            // Check if backups exist
            $results = DB::table('lookup')
            ->where('lookup_eventid' ,'=', $eventid)
            ->where('lookup_id' ,'=', "backup-files")
            ->get();

            $count = count($results);

            // Update or insert
            if($count == 1) {
                $response = $this->update_lookup($existingFilesString, $eventid);
            }
            else {
                $response = $this->insert_lookup($existingFilesString, $eventid);
            }

            return $response;        

        }
        catch(Exception $ex) {
            //Send error response
            $response = array(
                'status' => 'fail',
                'message' => 'There was an error creating backup file'
            );
            return $response;   
        }        
    }

    private function insert_lookup($existingFilesString, $eventid) {

        try {

            $lookup_value = $this->strip_string($existingFilesString, $eventid);

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
                'message' => 'New backup completed successfully',
                'backupFiles' => $lookup_value
            );

			return $response;            
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);

            $response = array(
                'status' => 'fail',
                'message' => 'ERROR: ' . $error
            );

			return $response;
		}

    }

    private function update_lookup($existingFilesString, $eventid) {

        try {

            $lookup_value = $this->strip_string($existingFilesString, $eventid);
            
            $uxtime = $this->unixTime();

            // Update lookup entry
            $result = DB::table('lookup')
            ->where('lookup_id', "backup-files")
            ->where('lookup_eventid', $eventid)
            ->update([
                'lookup_value' => $lookup_value,
                'lookup_uxtime' => $uxtime,
            ]);

            $response = array(
                'status' => 'success',
                'message' => 'New backup completed successfully',
                'backupFiles' => $lookup_value
            );

			return $response;            
        }
        //Return error to the client
        catch(Exception $ex) {
            $errorResponse = $this->errorException($ex);

            $response = array(
                'status' => 'fail',
                'message' => 'ERROR: ' . $error
            );

			return $response;
		}

    }  
    
    private function strip_string($string, $eventid) {

        $strip1 = "backup_" . $eventid . "_";
        $strip2 = ".txt";

        $string = str_replace($strip1, "", $string);
        $string = str_replace($strip2, "", $string);

        return $string;
    }
}




// Maximum number of files to keep
//$maxFiles = 5;

// Create the full path to the file
//$globString = "backup_" . $eventid . "_*.txt";

// Create string of backup filenames
// $existingFilesArr = glob($globString);
//$existingFilesString = implode('|', $existingFilesArr);

// Perform housekeeping
//$existingFiles = glob($globString);
//if (count($existingFiles) >= $maxFiles) {

//    $this->writeToLog('TIME FOR HOUSEKEEPING');

// Sort the files by modified time (oldest first)
//    array_multisort(
//        array_map('filemtime', $existingFiles),
//       SORT_ASC,
//        $existingFiles
//    );

// Select older file
//    $oldestFile = $existingFiles[0];

// Delete the oldest file                
//    if (unlink($oldestFile)) {

//Write to Activity Log
//        $this->writeToLog("Oldest file deleted: $oldestFile" . PHP_EOL);
//    } else {

//Write to Error Log
//        $this->writeToErrorLog("Error deleting the oldest file." . PHP_EOL);
//    }
//}
