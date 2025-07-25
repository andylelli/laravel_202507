<?php

namespace App\Classes;
use App\Classes\Traits\General;
use DB;

class QR_Code {

	use General;

    /** @var object of the qr lists */
    private $qr;
    // Google Chart API URL
    private $googleChartAPI = 'http://chart.apis.google.com/chart';
    // Code data
    private $codeData;

   /** Create new qr codes **/
    public function new_qr_create($email, $eventid, $guestid, $token, $string){

        //generate a new QR image and save
        $img = $this->generate_qr_image_url($string);
        $this->writeToLog($img);
        $type = 'png';
        $base64 = 'data:image/' . $type . ';base64,' . $img;

        $insertid = $this->insertQRCode($string, $base64, $eventid, $guestid);

        if($insertid) {
            $response = array(
                'qrcode_status' => 'success',
                'qrcode_id' => $insertid,
                'qrcode_value' => $string,
                'qrcode_image' => $base64,
                'qrcode_message' => 'New guest added'
            );
            return $response;
        }
        else {
            $response = array(
                'qrcode_status' => 'fail',
                'qrcode_message' => 'Error inserting QR code!'
            );
            return $response;
        }
    }

    /** Create a QR image containing a URL  */
    public function generate_qr_image_url($qrcode) {
        $this->url($qrcode);
        $img = $this->qrCode(250);
        return $img;
    }

    /** Create a QR image containing a Text  */
    public function generate_qr_image_text($qrcode) {
        $this->text($qrcode);
        $img = $this->qrCode(250);
        return $img;
    }

    /**
    * Create a new qrcode and confirm it is unique
    * @return new QR code.
    */
    public function setQRCodeString($event_name, $eventid) {

        $table = "static_data";
        $property = "url";

		$host = $this->getJSONParams($table, $property);

        $string = $host . $event_name;

        return $string;
    }

    /**
    * Check if QR code exists already *
    * @param string $code.            *
    * @return int $count.             *
    */
    public function checkQRCode($value, $eventid){

			$results = DB::table('qrcode')
			->where('qrcode_value' ,'=', $value)
            ->where('qrcode_eventid' ,'=', $eventid)
			->get();

			$count = count($results);

            if ($count == 0) {
                return false;
            } else {
               return true;
            }
    }

    /** Generate QR code image from Google webservice */
    private function qrCode($size = 200, $filename = null) {
        $ch = curl_init();
        $string = $this->codeData;

        curl_setopt($ch, CURLOPT_URL, $this->googleChartAPI);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "chs={$size}x{$size}&cht=qr&chl=" . urlencode($this->codeData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $ret_val = curl_exec($ch);
        $b64_image_data =  chunk_split(base64_encode($ret_val));
        curl_close($ch);

        return $b64_image_data;
    }

    /** Insert QR Code into DB */
    private function insertQRCode($qrcode, $base64, $eventid, $guestid){

		$uxtime = $this->unixTime();

		$insertid = DB::table('qrcode')->insertGetId([
			'qrcode_value' => $qrcode,
			'qrcode_image' => $base64,
			'qrcode_eventid' => $eventid,
			'qrcode_guestid' => $guestid,
			'qrcode_uxtime' => $uxtime
		]);

        if ($insertid) {
            return $insertid;
		}
        else {
            return false;
        }
    }

    /** Encode QR code as URL */
    private function url($url = null){
        $this->codeData = preg_match("#^https?\:\/\/#", $url) ? $url : "http://{$url}";
    }


    /** Encode QR code as Text */
    private function text($text){
        $this->codeData = $text;
    }
}
?>
