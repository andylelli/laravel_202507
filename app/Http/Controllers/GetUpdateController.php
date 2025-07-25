<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Traits\General;
use App\Classes\_Directory;
use App\Classes\Poll;

class GetUpdateController extends Controller
{
	use General;

    public function getEvents()
    {
        try {
            //$this->writeToLog(print_r(phpinfo(), true));
            $event_results = DB::table('event')
            ->get();

            $len = count($event_results);
            $i=0;

            for ($i = 0; $i < $len; $i++) {

                //unset($event_results[$i]->event_image);
                unset($event_results[$i]->event_userid);
                unset($event_results[$i]->event_image);

            }

            $response[] = array(
                'status' => 'success',
                'data' => $event_results
            );

		}catch(Exception $ex) {
			$error = $ex->getMessage();

			$response[] = array(
				'status' => 'fail',
				'message' => 'ERROR: ' . $error
			);

			return response()->json($response, 400);
		}

		return response()->json($response, 200);
	}

    public function getUpdatesForUser($userid, $unixtime)
    {

		try {
            $results = DB::table('user')
            ->where('user_id' ,'=', $userid)
            ->where('user_uxtime' ,'>', $unixtime)
            ->get();

            $response[] = array(
                'status' => 'success',
                'data' => $results
            );

		}catch(Exception $ex) {
			$error = $ex->getMessage();

			$response[] = array(
				'status' => 'fail',
				'message' => 'ERROR: ' . $error
			);

			return response()->json($response, 400);
		}

		return response()->json($response, 200);
	}

    public function getUpdatesForEvent($eventid, $unixtime)
    {
        try {
            //$this->writeToLog(print_r(phpinfo(), true));
            $results = DB::table('event')
            ->where('event_id' ,'=', $eventid)
            ->where('event_uxtime' ,'>', $unixtime)
            ->get();

            $response[] = array(
                'status' => 'success',
                'data' => $results
            );

		}catch(Exception $ex) {
			$error = $ex->getMessage();

			$response[] = array(
				'status' => 'fail',
				'message' => 'ERROR: ' . $error
			);

			return response()->json($response, 400);
		}

		return response()->json($response, 200);
	}

    public function getUpdatesForGeneric($table, $eventid, $unixtime, Request $request)
    {
		$role = $request->header('Role');

		try {
			$results = DB::table($table)
			->where($table . '_eventid' ,'=', $eventid)
			->where($table . '_uxtime' ,'>', $unixtime)
			->get();

            if($table == 'directoryentry' && $role != "admin") {
                $directory = new _Directory();
                $results = $directory->remove_hidden_directoryentry_names($results, $eventid);
            }

            if($table == 'lookup') {
                $lookup_item = "schedule-qr-codes";
                $len = count($results);
                $found = false;

                for ($x = 0; $x < $len; $x++) {
                    if ($results[$x]->lookup_id == $lookup_item) {
                        $found = true;
                        $index = $x;
                    }
                }

                $results = $results->toArray();

                if($found == true) {
                    unset($results[$index]);
                    $results = array_values($results);
                }
            }

			$response[] = array(
				'status' => 'success',
				'data' => $results
			);

		}catch(Exception $ex) {
			$error = $ex->getMessage();

			$response[] = array(
				'status' => 'fail',
				'message' => 'ERROR: ' . $error
			);

			return response()->json($response, 400);
		}

		return response()->json($response, 200);
	}

    public function getQRCodeGuest($guestid)
    {
		try {
            $results = DB::table('qrcode')
            ->where('qrcode_guestid' ,'=', $guestid)
            ->get();

            $response[] = array(
                'status' => 'success',
                'data' => $results
            );

        }catch(Exception $ex) {
            $error = $ex->getMessage();

            $response[] = array(
                'status' => 'fail',
                'message' => 'ERROR: ' . $error
            );

            return response()->json($response, 400);
        }

        return response()->json($response, 200);
    }

    public function getQRCodeEvent($eventid)
    {
        try {
            $results = DB::table('qrcode')
            ->where('qrcode_eventid' ,'=', $eventid)
            ->get();

            $response[] = array(
                'status' => 'success',
                'data' => $results
            );
        }catch(Exception $ex) {
            $error = $ex->getMessage();

            $response[] = array(
                'status' => 'fail',
                'message' => 'ERROR: ' . $error
            );

            return response()->json($response, 400);
        }

        return response()->json($response, 200);
    }

    public function getPollSubmit($pollitemid, $pollid, $eventid, $token, $guestid)
    {
        try {
            $pollObj = DB::table('poll')
            ->where('poll_id' ,'=', $pollid)
            ->get();

            $poll = get_object_vars($pollObj[0]);
            $poll_live = $poll['poll_live'];

            if($poll_live == 0) {
                $response = array(
                    'status' => 'disabled',
                    'message' => 'This poll is not currently running'
                );

                return response()->json($response, 200);
            }

            $results = DB::table('pollitem')
            ->where('pollitem_id' ,'=', $pollitemid)
            ->where('pollitem_pollid' ,'=', $pollid)
            ->where('pollitem_eventid' ,'=', $eventid)
            ->get();

            $count = count($results);

            if($count == 1) {
                $poll = new Poll();
                $response = $poll->submit_vote($pollitemid, $pollid, $eventid, $guestid);

                if($response['status'] == 'success') {
                    return response()->json($response, 200);
                }
                else {
                    $response = array(
                        'status' => 'fail',
                        'message' => 'There was an error submitting your vote'
                    );
                    return response()->json($response, 400);
                }
            }
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

    public function getPollScore($pollid, $eventid, $token, $guestid)
    {
        try {

            $pollObj = DB::table('poll')
            ->where('poll_id' ,'=', $pollid)
            ->get();

            $poll = get_object_vars($pollObj[0]);
            $poll_live = $poll['poll_live'];

            $pollitems = DB::table('pollitem')
            ->where('pollitem_pollid' ,'=', $pollid)
            ->where('pollitem_eventid' ,'=', $eventid)
            ->get();

            $pollvoteObj = DB::table('pollscore')
            ->where('pollscore_pollid' ,'=', $pollid)
            ->where('pollscore_guestid' ,'=', $guestid)
            ->get();

            if(count($pollvoteObj) == 1) {
                $pollvote = get_object_vars($pollvoteObj[0]);
                $poll_voteitem = $pollvote['pollscore_pollitemid'];

            }
            else {
                $poll_voteitem = false;
            }

            $response[] = array(
                'status' => 'success',
                'poll_live' => $poll_live,
            );

            foreach ($pollitems as $pollitemObj) {

                $pollitem = get_object_vars($pollitemObj);

                $poll = new Poll();
                $count = $poll->get_poll_count($pollid, $pollitem['pollitem_id']);

                if($pollitem['pollitem_id'] == $poll_voteitem) {
                    $vote = true;
                }
                else {
                    $vote = false;
                }

                if($count === false) {

                    unset($response);
                    $response = array(
                        'status' => 'fail',
                        'message' => 'There was an error retieving the poll counts'
                    );

                    return response()->json($response, 400);
                }
                else {

                    $response[] = array(
                        'pollitem_id' => $pollitem['pollitem_id'],
                        'vote' => $vote,
                        'count' => $count
                    );
                }
            }
            return response()->json($response, 200);
        }

        //Return error to the client
        catch(Exception $ex) {

            $error = $ex->getMessage();
            $this->writeToLog($error);

            $response = array(
                'status' => 'fail',
                'message' => $error
            );

            return response()->json($response, 400);
        }
	}
}
