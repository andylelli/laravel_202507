<?php

namespace App\Http\Controllers;
use DB;
use Exception;
use Illuminate\Http\Request;
use App\Classes\Traits\General;

class GetWebPermissionsController extends Controller
{
    use General;

	public function getWebPermissionsPage($eventnameURL, $eventtoken, $bgcolor, $type)
    {


		try {
        	$event = DB::table('event')
            ->where('event_token', $eventtoken)
            ->get();

			$lowercaseString = strtolower($eventnameURL);
            $decodedString = html_entity_decode($lowercaseString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $formattedStringHyphen = str_replace(' ', '-', $decodedString);
            $formattedEventNameURL = preg_replace('/[^a-zA-Z0-9]/', '', $formattedStringHyphen);

			$lowercaseString = strtolower($event[0]->event_name);
            $decodedString = html_entity_decode($lowercaseString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $formattedStringHyphen = str_replace(' ', '-', $decodedString);
            $formattedEventNameDB = preg_replace('/[^a-zA-Z0-9]/', '', $formattedStringHyphen);

			if ($formattedEventNameDB != $formattedEventNameURL) {
                $error = "Event name does not match the event ID";
                $this->writeToLog($error);
                
                throw new Exception($error); // This triggers the catch block
            }

			$lowercaseString = strtolower($event[0]->event_name);
            $decodedString = html_entity_decode($lowercaseString, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$eventnameDBURLEncode = rawurlencode($decodedString);
            $eventnameDBHTMLDecode = str_replace('%20', '-', $eventnameDBURLEncode);

            $eventName = html_entity_decode($event[0]->event_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			$croppedImage = $this->cropToSquare($event[0]->event_image);


			$url ="https://www.evaria.io/user/index.html?name=" . $eventnameDBHTMLDecode . "&token=" . $event[0]->event_token . "&id=" . $event[0]->event_id ."&bg=" . $bgcolor . "&type=" . $type;

			//$this->writeToLog(print_r($event, true));
        	return view('permission', ['url' => $url, 'id' => $event[0]->event_id, 'eventtoken' => $event[0]->event_token, 'eventname' => $eventName, 'eventimage' => $croppedImage, 'bgcolor' => $bgcolor]);
		}

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

	private function cropToSquare($base64Image) {
		// Remove the 'data:image/*;base64,' prefix if it exists
		$prefix = 'data:image/';
		if (strpos($base64Image, $prefix) === 0) {
			$commaPosition = strpos($base64Image, ',');
			if ($commaPosition !== false) {
				$base64Image = substr($base64Image, $commaPosition + 1);
			}
		}
	
		// Decode the Base64 string to get the image data
		$imageData = base64_decode($base64Image);
		
		// Create an image resource from the decoded data
		$image = imagecreatefromstring($imageData);
		if (!$image) {
			die('Invalid image data');
		}
	
		// Get the dimensions of the original image
		$width = imagesx($image);
		$height = imagesy($image);
	
		// Determine the size of the square (smallest dimension)
		$squareSize = min($width, $height);
	
		// Calculate the coordinates for the center cropping
		$x = ($width - $squareSize) / 2;  // Horizontal center
		$y = ($height - $squareSize) / 2; // Vertical center
	
		// Create a new image resource for the cropped square
		$croppedImage = imagecreatetruecolor($squareSize, $squareSize);
	
		// Copy the central part of the original image to the new image
		imagecopy($croppedImage, $image, 0, 0, $x, $y, $squareSize, $squareSize);
	
		// Detect the image type and start outputting the image to a variable
		ob_start(); // Start output buffering
		$imageInfo = getimagesizefromstring($imageData);
	
		if ($imageInfo === false) {
			die('Error: Unable to detect image type');
		}
	
		// Dynamically output the image to the buffer based on the mime type
		if ($imageInfo['mime'] == 'image/png') {
			imagepng($croppedImage); // Output as PNG
		} elseif ($imageInfo['mime'] == 'image/jpeg' || $imageInfo['mime'] == 'image/jpg') {
			imagejpeg($croppedImage); // Output as JPEG or JPG
		} elseif ($imageInfo['mime'] == 'image/gif') {
			imagegif($croppedImage); // Output as GIF
		} else {
			die('Unsupported image type');
		}
	
		// Capture the output and encode it to Base64
		$croppedImageData = ob_get_contents();
		ob_end_clean(); // End output buffering
	
		// Encode the image data as base64
		$base64CroppedImage = base64_encode($croppedImageData);
	
		// Clean up
		imagedestroy($image);
		imagedestroy($croppedImage);
	
		// Return the base64 encoded image
		return 'data:' . $imageInfo['mime'] . ';base64,' . $base64CroppedImage;
	}
	
}
