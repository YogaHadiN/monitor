<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use App\Http\Controllers\WablasController;
use Input;

class FonnteSecondController extends Controller
{
    //
    public function getWebhook(){
        /* Log::info( env('FONNTE_TOKEN') ); */
        Log::info('fonnte');
        Log::info('getWebhook');
        Log::info('yogggaaaa');
        /* $this->webhook(); */
    }
    public function postWebhook(){
        $json      = file_get_contents('php://input');
        $data      = json_decode($json, true);
        if (!isset($data['sender']) || !isset($data['message'])) {
            Log::error('Invalid payload', $data);
            return;
        } else {
            /* $this->webhook(); */
        }




        /* $json = file_get_contents('php://input'); */
        /* $data = json_decode($json, true); */
        /* $device = $data['device']; */
        $no_telp = $data['sender'];
        /* $message = $data['message']; */
        /* $member= $data['member']; //group member who send the message */
        /* $name = $data['name']; */
        /* $location = $data['location']; */
        /* //data below will only received by device with all feature package */
        /* //start */
        /* $url =  $data['url']; */
        /* $filename =  $data['filename']; */
        /* $extension=  $data['extension']; */

        /* $data = compact( */
        /*     'json' , */
        /*     'data' , */
        /*     'device' , */
        /*     'sender' , */
        /*     'message' , */
        /*     'member', */
        /*     'name' , */
        /*     'location' , */
        /*     'url' , */
        /*     'filename' , */
        /*     'extension' */
        /* ); */

        /* Log::info($data); */
        /* sendFonnte($sender, $reply); */




    }
    public function getChaning(){
    }
    public function postChaning(){
    }
    public function getStatus(){
    }
    public function postStatus(){

    }
    public function getConnect(){
    }
    public function postConnect(){
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function webhook()
    {
        header('Content-Type: application/json; charset=utf-8');
        $json      = file_get_contents('php://input');
        $data      = json_decode($json, true);
        $device    = $data['device'];
        $sender    = $data['sender'];
        $message   = $data['message'];
        $text      = $data['text']; //button text
        $member    = $data['member']; //group member who send the message
        $name      = $data['name'];
        $location  = $data['location'];

        //data below will only received by device with all feature package
        //start
        $url       = $data['url'];
        $filename  = $data['filename'];
        $extension = $data['extension'];
        //end


        $reply = [
            'message' => null
        ];
        if ( $message == "test" ) {
            $reply = [
                "message" => "working great!",
            ];
        } elseif ( $message == "image" ) {
            $reply = [
                "message" => "image message",
                "url" => "https://filesamples.com/samples/image/jpg/sample_640%C3%97426.jpg",
            ];
        } elseif ( $message == "audio" ) {
            $reply = [
                    "message" => "audio message",
                "url" => "https://filesamples.com/samples/audio/mp3/sample3.mp3",
                "filename" => "music",
            ];
        } elseif ( $message == "video" ) {
            $reply = [
                "message" => "video message",
                "url" => "https://filesamples.com/samples/video/mp4/sample_640x360.mp4",
            ];
        } elseif ( $message == "file" ) {
            $reply = [
                "message" => "file message",
                "url" => "https://filesamples.com/samples/document/docx/sample3.docx",
                "filename" => "document",
            ];
        } else {
            /* $reply = [ */
            /*     "message" => "Sorry, i don't understand. Please use one of the following keyword : */
            /*         Test */
            /*         Audio */
            /*         Video */
            /*         Image */
            /*         File", */
            /*     ]; */
            /* $reply = [ */
            /*     "message" => $message */
            /* ]; */
        }


        $wablas               = new WablasController;
        $wablas->room_id      = null;
        $wablas->no_telp      = $sender;
        $wablas->message_type = isset( $url ) ? 'image' : 'text';
        $wablas->image_url = isset($url) ? $url : null;
        $wablas->message      = strtolower($message);
        $wablas->fonnte      = true;
        $wablas->webhook();



    }

    public function sendFonnte($target, $data) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.fonnte.com/send",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => array(
                'target'   => $target,
                'message'  => isset( $data['message'] ) ? $data['message'] : null,
                'url'      => isset( $data['url'] ) ? $data['url'] : null,
                'filename' => isset( $data['filename'] ) ? $data['filename'] : null,
            ),
          CURLOPT_HTTPHEADER => array(
            "Authorization: " . env('FONNTE_TOKEN')
          ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) {
            Log::error('Fonnte API failed', [
                'response' => $response,
                'error' => curl_error($curl),
                'code' => $httpCode,
            ]);
        }
        curl_close($curl);

        return $response;
    }
}
