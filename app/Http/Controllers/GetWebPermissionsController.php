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
			->first(); // returns a single object

		if (!$event) {
			throw new Exception("Event not found");
		}

		// Decode both names
		$eventNameDB_decoded = html_entity_decode($event->event_name, ENT_QUOTES | ENT_XML1);
		$eventnameURL_decoded = urldecode($eventnameURL);

		// ✅ Log both values before comparison
		$this->writeToLog("Decoded DB event name: {$eventNameDB_decoded}");
		$this->writeToLog("Decoded URL event name: {$eventnameURL_decoded}");

		// Compare
		if ($eventnameURL_decoded !== $eventNameDB_decoded) {
			$error = "Event name does not match the event ID";
			$this->writeToLog($error);
			throw new Exception($error);
		}

		// Proceed with rest
		$croppedImage = $this->cropToSquare($event->event_image);

		$url = "https://www.evaria.io/user/index.html?name={$eventnameURL}&token={$event->event_token}&id={$event->event_id}&bg={$bgcolor}&type={$type}";

		return view('permission', [
			'url' => $url,
			'id' => $event->event_id,
			'eventtoken' => $event->event_token,
			'eventname' => $eventnameURL_decoded,
			'eventimage' => $croppedImage,
			'bgcolor' => $bgcolor,
		]);

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
