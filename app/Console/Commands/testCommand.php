<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class testCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually testing command';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $message = 'oke';
        dd( 'oke' );
        $this->singleTextSend($message);
    }
	/**
	* undocumented function
	*
	* @return void
	*/
	private function multipleSendWablas($message)
	{
		$curl    = curl_init();
		$token   = env('WABLAS_TOKEN');
		$payload = [
			"data" => [
				[
					'phone'   => '6281381912803',
					'message' => $message,
					'secret'  => false, // or true
					'retry'   => false, // or true
					'isGroup' => false, // or true
				],
				[
					'phone'   => '628111842351',
					'message' => $message,
					'secret'  => false, // or true
					'retry'   => false, // or true
					'isGroup' => false, // or true
				]
			]
		];
		curl_setopt($curl, CURLOPT_HTTPHEADER,
			array(
				"Authorization: $token",
				"Content-Type: application/json"
			)
		);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload) );
		curl_setopt($curl, CURLOPT_URL,  "https://pati.wablas.com/api/v2/send-message");
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

		$result = curl_exec($curl);
		curl_close($curl);
		echo "<pre>";
		print_r($result);
	}

	/**
	* undocumented function
	*
	* @return void
	*/
	private function singleTextSend($message)
	{
		$curl = curl_init();
		$token = env('WABLAS_TOKEN');
		$data = [
			'phone'   => '6281381912803',
			'message' => $message,
			'isGroup' => 'true',
		];
		curl_setopt($curl, CURLOPT_HTTPHEADER,
			array(
				"Authorization: $token",
			)
		);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($curl, CURLOPT_URL,  "https://pati.wablas.com/api/send-message");
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

		$result = curl_exec($curl);
		curl_close($curl);
		echo "<pre>";
		print_r($result);
	}
	
	
}
