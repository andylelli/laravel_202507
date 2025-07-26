<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Classes\Traits\General;

class GetManifestController extends Controller
{
	use General;

    public function getManifest($eventid)
    {
        try {
            $results = DB::table('event')
            ->where('event_id' ,'=', $eventid)
            ->get();

            $name = $results[0]->event_name;
            $token = $results[0]->event_token;

            $lowercaseString = strtolower($name);
            $decodedString = html_entity_decode($lowercaseString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $formattedStringHyphen = str_replace(' ', '-', $decodedString);
            $iconFolder = preg_replace('/[^a-zA-Z0-9-]/', '', $formattedStringHyphen);


            $lowercaseString = strtolower($name);
            $decodedString = html_entity_decode($lowercaseString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $formattedStringHyphen = str_replace(' ', '-', $decodedString);
            $eventName = rawurlencode($formattedStringHyphen);

            //$this->writeToLog($eventName);

            
            $folderPath = "user/icons/" . $iconFolder;

            $lookup = DB::table('lookup')
            ->where('lookup_eventid', '=', $eventid)
            ->where('lookup_id', '=', 'splashcolour')
            ->first();

            $relativePath = "user/icons/" . $iconFolder;
            $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;

            
            if (!is_dir($absolutePath)) {
                $convertedString = "evaria";
            } else {
                $convertedString = $iconFolder;
            }
           

            // CREATE ICONS
            $icon128[] = array(
                'src' => '/user/icons/' . $convertedString . '/128x128.png',
                'sizes' => '128x128',
                'type' => 'image/png'                                                                                                                                                        
            );
            $icon144[] = array(
                'src' => '/user/icons/' . $convertedString . '/144x144.png',
                'sizes' => '144x144',
                'type' => 'image/png'                                                                                                                                                        
            );
            $icon152[] = array(
                'src' => '/user/icons/' . $convertedString . '/152x152.png',
                'sizes' => '152x152',
                'type' => 'image/png'                                                                                                                                                        
            );
            $icon192[] = array(
                'src' => '/user/icons/' . $convertedString . '/192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png'                                                                                                                                                        
            );      
            $icon256[] = array(
                'src' => '/user/icons/' . $convertedString . '/256x256.png',
                'sizes' => '256x256',
                'type' => 'image/png'                                                                                                                                                        
            );  
            $icon512[] = array(
                'src' => '/user/icons/' . $convertedString . '/512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png'                                                                                                                                                        
            );            
            $iconsArray = array($icon128[0], $icon144[0], $icon152[0], $icon192[0], $icon256[0], $icon512[0]);  

            // CREATE SCREENSHOTS
            $screenshotNarrow[] = array(
                'src' => '/user/icons/screenshot.png',
                'type' => 'image/png',
                'sizes' => '420x943',
                'form_factor' => 'narrow',
                'label' =>  'App'                                                                                                                                                       
            );  
            $screenshotWide[] = array(
                'src' => '/user/icons/screenshot.png',
                'type' => 'image/png',
                'sizes' => '420x943',
                'form_factor' => 'wide',
                'label' =>  'App'                                                                                                                                                     
            );            
            $screenshotsArray = array($screenshotWide[0], $screenshotNarrow[0]); 
            
            $colour = $lookup ? $lookup->lookup_value : '000000';
            
            //$startURL = "/user/event/" . $convertedString;
            $startURL = "/user/index.html?name=" . $eventName . "&token=" . $token . "id=" . $eventid . "&bg=" . $colour;

            // CREATE MAIN RESPONSE
            $response[] = array(
                'background-color' => '#2b2b2b',
                'description' => $name,
                'display' => 'standalone',
                'icons' => $iconsArray,
                'id' => 'evaria-123456',
                'lang' => 'en-US',
                'name' => $name,
                'orientation' => 'portrait',
                'screenshots' => $screenshotsArray,
                'short_name' => $name,
                'start_url' => $startURL,  
                'theme_color' => '#2b2b2b',                                                                                                                                                         
            );

        }catch(Exception $ex) {
            $error = $ex->getMessage();

            $response[] = array(
                'status' => 'fail',
                'message' => 'ERROR: ' . $error
            );

            return response()->json($response, 400);
        }

        return response()->json($response[0] , 200);
    }
}
