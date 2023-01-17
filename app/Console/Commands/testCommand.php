<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\WablasController;
use App\Models\WhatsappBpjsDentistRegistration;
use App\Models\WhatsappMainMenu;
use App\Models\CekListDikerjakan;
use App\Models\WhatsappBot;

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

        /* $whatsapp_bot = WhatsappBot::with('staf') */
        /*     ->where('no_telp', '6281381912803') */
        /*     ->whereRaw('whatsapp_bot_service_id = 1 or whatsapp_bot_service_id = 2') */
        /*     ->where('created_at', 'like', date('Y-m-d'). '%') */
        /*     ->first(); */
        /* dd( $whatsapp_bot ); */
        $this->refreshCekHarian();
    }
    /**
     * undocumented function
     *
     * @return void
     */
    private function refreshCekHarian()
    {
        /* CekListDikerjakan::truncate(); */
        WhatsappBot::truncate();

        WhatsappBot::create([
            'whatsapp_bot_service_id' => 1,
            'staf_id'                 => 11,
            'no_telp'                 => '6281381912803'
        ]);

    }
    

    /**
     * undocumented function
     *
     * @return void
     */
    private function clearWaBot()
    {
        $no_telp =  '6281381912803';
        \App\Models\WhatsappComplaint::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappComplaint::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappRecoveryIndex::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappSatisfactionSurvey::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappMainMenu::where('no_telp', $no_telp)->delete();
        \App\Models\FailedTherapy::where('no_telp', $no_telp)->delete();
        \App\Models\KuesionerMenungguObat::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappBpjsDentistRegistration::where('no_telp', $no_telp)->delete();
        \App\Models\WhatsappJadwalKonsultasiInquiry::where('no_telp', $no_telp)->delete();
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
