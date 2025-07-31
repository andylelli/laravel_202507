<?php

namespace App\Http\Controllers;

use DB;
use App\Classes\Traits\General;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class PostImageController extends Controller
{
	use General;

	public function postImage(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'image' => 'required|image|max:2000', // max size in kilobytes (2MB)
			]);

			if ($validator->fails()) {
				throw new HttpResponseException(response()->json([
					'status' => 'fail',
					'message' => $validator->errors()->first('image')
				], 422));
			}

			$data = $request->all();
			$id = $data['id'];
			$table = $data['table'];

			$picName = $request->file('image')->getClientOriginalName();
			$destinationPath = 'uploads/images/';
			$path = $request->file('image')->move($destinationPath, $picName);

			$uxtime = $this->unixTime();
			$image = $this->generalBase64($path);

			if ($table == 'event') {
				$base64Image = preg_replace('/^data:image\/[^;]+;base64,/', '', $image);
				$this->createPwaIcons($base64Image, '/home/1159228.cloudwaysapps.com/gthewnsykf/public_html/user/icons/' . $id);
			}

			unlink($destinationPath . $picName);

			$results = DB::table($table)
				->where($table . '_id', $id)
				->update([
					$table . '_image' => $image,
					$table . '_uxtime' => $uxtime
				]);

			if ($results) {
				return response()->json([
					'status' => 'success',
					'base64image' => $image,
					'message' => 'Image uploaded successfully'
				], 200);
			} else {
				return response()->json([
					'status' => 'fail',
					'message' => 'Error uploading image'
				], 400);
			}

		} catch (HttpResponseException $e) {
			// Return validation error response
			return $e->getResponse();
		} catch (\Exception $e) {
			// General exception handling
			return response()->json([
				'status' => 'fail',
				'message' => 'An unexpected error occurred: ' . $e->getMessage()
			], 500);
		}
	}

	private function createPwaIcons($base64Image, $outputDir) {
		{
			// List of sizes for PWA icons
			$sizes = [
				128, 144, 152, 196, 256, 512
			];
		
			// Check if the output directory exists, if not, create it
			if (!is_dir($outputDir)) {
				mkdir($outputDir, 0777, true);
			}
		
			// Decode the base64 image
			$imageData = base64_decode($base64Image);
		
			// Create an image resource from the decoded data
			$sourceImage = imagecreatefromstring($imageData);
		
			if ($sourceImage === false) {
				die('Failed to create image from base64 data');
			}
		
			// Get the original image width and height
			$sourceWidth = imagesx($sourceImage);
			$sourceHeight = imagesy($sourceImage);
		
			// Determine the size of the square crop
			$cropSize = min($sourceWidth, $sourceHeight);
		
			// Calculate the starting points for the crop
			$xOffset = ($sourceWidth - $cropSize) / 2;
			$yOffset = ($sourceHeight - $cropSize) / 2;
		
			// Create a new image resource for the cropped square image
			$croppedImage = imagecreatetruecolor($cropSize, $cropSize);
		
			// Crop the original image to a square
			imagecopyresampled($croppedImage, $sourceImage, 0, 0, $xOffset, $yOffset, $cropSize, $cropSize, $cropSize, $cropSize);
		
			// Generate and save icons for each specified size
			foreach ($sizes as $size) {
				// Create a new true color image with the desired size
				$icon = imagecreatetruecolor($size, $size);
		
				// Resize the cropped image and copy it to the new image resource
				imagecopyresampled($icon, $croppedImage, 0, 0, 0, 0, $size, $size, $cropSize, $cropSize);
		
				// Save the icon to the output directory
				$outputFile = $outputDir . "/{$size}x{$size}.png";
				imagepng($icon, $outputFile);
		
				// Free memory associated with the new image resource
				imagedestroy($icon);
			}

			//apple-touch-icon
			// Create a new true color image with the desired size
			$icon = imagecreatetruecolor(256, 256);

			// Resize the cropped image and copy it to the new image resource
			imagecopyresampled($icon, $croppedImage, 0, 0, 0, 0, 256, 256, $cropSize, $cropSize);
	
			// Save the icon to the output directory
			$outputFile = $outputDir . "/apple-touch-icon.png";
			imagepng($icon, $outputFile);
	
			// Free memory associated with the new image resource
			imagedestroy($icon);


			//favicon
			// Create a new true color image with the desired size
			$icon = imagecreatetruecolor(128, 128);

			// Resize the cropped image and copy it to the new image resource
			imagecopyresampled($icon, $croppedImage, 0, 0, 0, 0, 128, 128, $cropSize, $cropSize);
	
			// Save the icon to the output directory
			$outputFile = $outputDir . "/favicon.png";
			imagepng($icon, $outputFile);
	
			// Free memory associated with the new image resource
			imagedestroy($icon);

		
			// Free memory associated with the cropped image and the source image
			imagedestroy($croppedImage);
			imagedestroy($sourceImage);
		
		}
		
		// Example usage:
		$base64Image = 'your_base64_encoded_image_here';
		$outputDir = 'path_to_your_output_directory';
	}

    public function postRemoveImage(Request $request)
    {
        $data = $request->all();
		$id = $request->id;
		$table = $request->table;

        $image = null;
        $uxtime = $this->unixTime();

		$results = DB::table($table)
			->where($table . '_id', $id)
			->update([$table . '_image' => $image, $table . '_uxtime' => $uxtime]);

		if($results == true){

			$response[] = array(
				'status' => 'success',
				'base64image' => $image,
				'message' => 'Image removed successfully'
			);
			return response()->json($response, 200);
		}
		else{

			$response[] = array(
				'status' => 'fail',
				'message' => 'Error removing image'
			);
			return response()->json($response, 400);
		}
	}
}
