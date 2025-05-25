<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use Input;

class FonnteController extends Controller
{
    public function getWebhook(){
        /* Log::info( env('FONNTE_TOKEN') ); */
        Log::info('fonnte');
        Log::info('getWebhook');
        Log::info('yogggaaaa');
        /* $this->webhook(); */
    }
    public function postWebhook(){
        Log::info('fonnte');
        Log::info('postWebhook');
        /* $this->webhook(); */
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $device = $data['device'];
        $sender = $data['sender'];
        $message = $data['message'];
        $member= $data['member']; //group member who send the message
        $name = $data['name'];
        $location = $data['location'];
        //data below will only received by device with all feature package
        //start
        $url =  $data['url'];
        $filename =  $data['filename'];
        $extension=  $data['extension'];

        $data = compact(
            'json' ,
            'data' ,
            'device' ,
            'sender' ,
            'message' ,
            'member',
            'name' ,
            'location' ,
            'url' ,
            'filename' ,
            'extension'
        );

        Log::info($data);
        $reply = 'apapap';
        sendFonnte($sender, $reply);

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
                'target' => $target,
                'message' => $data['message'],
                'url' => $data['url'],
                'filename' => $data['filename'],
            ),
          CURLOPT_HTTPHEADER => array(
            "Authorization: TOKEN"
          ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    public function getChaning(){
        Log::info('fonnte');
        Log::info('getChaining');
    }
    public function postChaning(){
        Log::info('fonnte');
        Log::info('postChaning');
    }
    public function getStatus(){
        Log::info('fonnte');
        Log::info('getStatus');
    }
    public function postStatus(){
        Log::info('fonnte');
        Log::info('postStatus');
    }
    public function getConnect(){
        Log::info('fonnte');
        Log::info('getStatus');
    }
    public function postConnect(){
        Log::info('fonnte');
        Log::info('postStatus');
    }

    /**
     * undocumented function
     *
     * @return void
     */

}
