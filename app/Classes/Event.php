<?php

namespace App\Classes;

use App\Classes\Traits\General;
use Illuminate\Support\Facades\DB;
use Exception;

class Event
{
    use General;

    /**
     * Create a new event, generate and save a unique event token.
     * @param array $params [event_name, user_id]
     * @return array
     */
    public function new_event_insert(array $params): array
    {
        [$name, $userId] = $params;
        $uxtime = $this->unixTime();
        $table = "event";
        $property = "dbInsertInitial";

        // Get default params and convert to array
        $dbInsertInitial = $this->getJSONParams($table, $property);
        $dbInsertArray = json_decode(json_encode($dbInsertInitial), true);

        // Generate a unique token for the event
        $eventToken = $this->getToken(32);

        // Add mandatory values, including the new event_token
        $dbInsertArray["event_name"] = $name;
        $dbInsertArray["event_userid"] = $userId;
        $dbInsertArray["event_uxtime"] = $uxtime;
        $dbInsertArray["event_token"] = $eventToken;

        try {
            // Insert new event and get event ID
            $eventId = DB::table($table)->insertGetId($dbInsertArray);

            // Optionally, insert into install table
            DB::table('install')->insertGetId([
                'install_count'   => 0,
                'install_eventid' => $eventId,
                'install_uxtime'  => $uxtime,
            ]);

            // Prepare and return API response
            return array_merge($dbInsertArray, [
                'status'     => 'success',
                'event_id'   => $eventId,
                'event_token'=> $eventToken,
                'message'    => 'New event added'
            ]);
        } catch (Exception $ex) {
            $this->writeToLog($ex->getMessage());
            return [
                'status'  => 'fail',
                'message' => $ex->getMessage()
            ];
        }
    }

    /**
     * Generate a random token (copied from _User for reuse)
     */
    public function getToken($length)
    {
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        $max = strlen($codeAlphabet);

        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max - 1)];
        }
        return $token;
    }
}
