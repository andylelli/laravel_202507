<?php

namespace App\Classes;

use App\Classes\Traits\General;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class _User
{
    use General;

    private $permittedAttempts = 99999;
    private $registrationCode = 'superstar';
    private $adminRole = 3;

    /**
     * Routes login attempts to the correct method.
     * Logs incoming email, password, and token.
     * @param string $email
     * @param string $password
     * @param string $token
     * @return object|array
     */
    public function login($email, $password, $token)
    {
        // Log the incoming credentials (WARNING: don't do this in production!)
        Log::info('LOGIN ATTEMPT', [
            'email' => $email,
            'password' => $password,
            'token' => $token,
        ]);

        if (strlen($password) > 0 && strlen($email) > 0) {
            return $this->loginWithPassword($email, $password);
        } elseif (strlen($token) > 0) {
            return $this->loginWithToken($token);
        } else {
            return (object)[
                'status' => 'fail',
                'message' => 'Please provide either email/password or event token.'
            ];
        }
    }

    /**
     * User login by email and password.
     * @param string $email
     * @param string $password
     * @return object
     */
    public function loginWithPassword(string $email, string $password)
    {
        $user = DB::table('user')
            ->where('user_email', $email)
            ->first();

        if ($user) {
            if (password_verify($password, $user->user_password)) {
                if ($user->user_wronglogins <= $this->permittedAttempts) {
                    $events = DB::table('event')
                        ->select('event_id', 'event_name', 'event_token')
                        ->where('event_userid', $user->user_id)
                        ->get();

                    $userObj = clone $user;
                    unset($userObj->user_password);

                    $userObj->status = 'success';
                    $userObj->message = 'You are now logged in.';
                    $userObj->eventslist = json_decode(json_encode($events), true);

                    return $userObj;
                } else {
                    return (object)[
                        'status' => 'fail',
                        'message' => 'This account is blocked, please contact our support team.'
                    ];
                }
            } else {
                return (object)[
                    'status' => 'fail',
                    'message' => 'Invalid password.'
                ];
            }
        } else {
            return (object)[
                'status' => 'fail',
                'message' => 'This user does not exist.'
            ];
        }
    }

    /**
     * Login by event token only.
     * @param string $token
     * @return object
     */
    public function loginWithToken(string $token)
    {
        $event = DB::table('event')
            ->where('event_token', $token)
            ->first();

        if ($event) {
            return (object)[
                'status'      => 'success',
                'event_id'    => $event->event_id,
                'event_name'  => $event->event_name,
                'event_token' => $event->event_token,
                'message'     => 'You are now logged in by event token.'
            ];
        } else {
            return (object)[
                'status' => 'fail',
                'message' => 'Invalid event token.'
            ];
        }
    }

    /**
     * Validate token for user or event.
     * @param string $token
     * @param string $eventid
     * @param string $userid
     * @param string $role
     * @return bool
     */
    public function validate_token($token, $eventid, $userid, $role)
    {
        if ($role == 'user') {
            // Check if the event's token matches and event is owned by user
            $results = DB::table('event')
                ->select('event_id')
                ->where('event_token', '=', $token)
                ->get();

            if (count($results) == 1) {
                return true;
            } else {
                return false;
            }
        } elseif ($role == 'admin') {
            // Check if user token matches
            $results = DB::table('user')
                ->select('user_token')
                ->where('user_id', '=', $userid)
                ->get();

            if (count($results) == 1) {
                $user_token = $results[0]->user_token;
                if ($user_token == $token) {
                    // Check if user owns the event or event is 'new'
                    $events = DB::table('event')
                        ->select('event_userid')
                        ->where('event_id', '=', $eventid)
                        ->where('event_userid', '=', $userid)
                        ->get();

                    if (count($events) == 1) {
                        return true;
                    } else {
                        return ($eventid == 'new');
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Register a new admin user account.
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @param string $password
     * @param string $code
     * @return array
     */
    public function registration($firstname, $lastname, $email, $password, $code): array
    {
        if (!(isset($firstname, $lastname, $password, $email, $code))) {
            return [
                'status' => 'fail',
                'message' => 'Please fill in all fields'
            ];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'fail',
                'message' => 'Invalid email'
            ];
        }
        if ($this->checkEmail($email)) {
            return [
                'status' => 'fail',
                'message' => 'This email is already registered'
            ];
        }
        if (!(strlen($password) > 7)) {
            return [
                'status' => 'fail',
                'message' => 'Password must be at least 8 characters'
            ];
        }
        if ($code != $this->registrationCode) {
            return [
                'status' => 'fail',
                'message' => 'Registration code is incorrect'
            ];
        }

        try {
            $uxtime = $this->unixTime();
            $hashPassword = $this->hashPass($password);
            $token = $this->getToken(32);

            DB::table('user')->insertGetId([
                'user_firstname' => $firstname,
                'user_lastname' => $lastname,
                'user_email' => $email,
                'user_password' => $hashPassword,
                'user_token' => $token,
                'user_defaulteventid' => 0,
                'user_role' => $this->adminRole,
                'user_uxtime' => $uxtime,
            ]);

            return [
                'status' => 'success',
                'message' => 'Your registration has been successful. Please log in.'
            ];
        } catch (Exception $ex) {
            return [
                'status' => 'fail',
                'message' => 'Registration error'
            ];
        }
    }

    /**
     * Check if email is already used.
     * @param string $email
     * @return bool
     */
    private function checkEmail(string $email): bool
    {
        return DB::table('user')
            ->where('user_email', $email)
            ->exists();
    }

    /**
     * Password hash function.
     * @param string $password
     * @return string Hashed password
     */
    private function hashPass(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Generate user login token.
     * @param int $length
     * @return string
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
