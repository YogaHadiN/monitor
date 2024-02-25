<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\Antrian;
use App\Models\AntrianPoli;
use Carbon\Carbon;
use Input;

class AntrianPoliController extends Controller
{
	public $input_pasien_id;
	public $input_antrian;
	public $input_asuransi_id;
	public $input_poli_id;
	public $input_staf_id;
	public $input_tanggal;
	public $input_jam;
	public $input_pasien;
	public $input_kecelakaan_kerja;
	public $input_self_register;
	public $input_nomor_asuransi;
	public $input_nomor_asuransi_bpjs;
	public $input_bukan_peserta;
	public $input_antrian_id;
    public $asuransi_is_bpjs;

	public function __construct() {
		$this->input_pasien_id           = Input::get('pasien_id');
		$this->input_nomor_asuransi      = Input::get('nomor_asuransi');
		$this->input_nomor_asuransi_bpjs = Input::get('nomor_asuransi_bpjs');
		$this->input_asuransi_id         = Input::get('asuransi_id');
		$this->input_poli_id             = Input::get('poli_id');
		$this->input_staf_id             = Input::get('staf_id');
		$this->input_tanggal             = datePrep( Input::get('tanggal') );
		$this->input_jam             = date('Y-m-d H:i:s');
		$this->input_bukan_peserta       = Input::get('bukan_peserta');
        /* $this->middleware('nomorAntrianUnik', ['only' => ['store']]); */
    }
	public function inputDataAntrianPoli(){
		$ap                                   = new AntrianPoli;
		$ap->asuransi_id                      = $this->input_asuransi_id;
		$ap->sudah_skrining_riwayat_kesehatan = !is_null( Input::get('sudah_skrining_riwayat_kesehatan') ) ? Input::get('sudah_skrining_riwayat_kesehatan') : 0;
		$ap->pasien_id                        = $this->input_pasien_id;
		$ap->poli_id                          = $this->input_poli_id;
		$ap->staf_id                          = $this->input_staf_id;
		$ap->jam_pasien_mulai_mengantri       = $this->jam_pasien_mulai_mengantri;
		if ( $this->asuransi_is_bpjs ) {
			$ap->bukan_peserta                = $this->input_bukan_peserta;
		}
        $ap->jam                              = !is_null( $this->input_antrian ) ? $this->input_antrian->created_at->format("Y-m-d H:i:s") : date("Y-m-d H:i:s");
		$ap->tanggal                          = $this->input_tanggal;
		$ap->save();

		return $ap;
	}
}
