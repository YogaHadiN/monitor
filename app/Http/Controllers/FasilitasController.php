<?php

namespace App\Http\Controllers;

use Input;

use DB;
use Auth;
use Log;
use Illuminate\Http\Request;
use App\Http\Controllers\PasiensController;
use App\Http\Controllers\AntrianPoliController;
use App\Http\Controllers\AntriansController;
use App\Http\Controllers\AntrianPeriksasController;
use App\Http\Requests;
use App\Models\Fasilitas;
use App\Models\TipeKonsultasi;
use App\Models\Pasien;
use App\Models\PoliBpjs;
use App\Models\Antrian;
use App\Models\Panggilan;
use App\Models\JurnalUmum;
use App\Models\Periksa;
use App\Models\AntrianPeriksa;
use App\Models\TransaksiPeriksa;
use App\Models\Kabur;
use App\Models\Staf;
use App\Models\Dispensing;
use App\Models\BukanPeserta;
use App\Models\AntrianPoli;
use App\Models\Ruangan;
use App\Models\Classes\Yoga;
use App\Models\RumahSakit;
use App\Models\Terapi;
use App\Models\Rujukan;
use App\Models\SuratSakit;
use App\Models\RegisterAnc;
use App\Models\GambarPeriksa;
use App\Models\Usg;
use App\Models\Sms;
use App\Models\DeletedPeriksa;


class FasilitasController extends Controller
{
	public $input_nomor_bpjs;
	public $input_ruangan_id;
    public $input_tipe_konsultasi_id;
    public $input_registrasi_pembayaran_id;
    public $input_staf_id;

	public function __construct(){
        $this->input_ruangan_id         = Input::get("ruangan_id");
        $this->input_tipe_konsultasi_id = Input::get("tipe_konsultasi_id");
        $this->input_staf_id            = Input::get("staf_id");
        $this->middleware('redirectBackIfIdAntrianNotFound', ['only' => ['prosesAntrian']]);
		$this->input_nomor_bpjs = Input::get('nomor_bpjs');
		$this->middleware('prolanisFlagging', ['only' => [
			 'postAntrianPoli'
		]]);
	}

	public function antrianPost(){
        $id                 = $this->input_ruangan_id;
        $tipe_konsultasi_id = $this->input_tipe_konsultasi_id;
        $staf_id            = $this->input_staf_id;

        if (is_null( $tipe_konsultasi_id )) {
            $ruangan = Ruangan::find( $id );
            if (is_null( $ruangan )) {
                $kdPoli    = Input::get('kodepoli');
                if (!empty( $kdPoli )) {
                    $poli_bpjs = PoliBpjs::where('kdPoli', $kdPoli)->first();
                    $tipe_konsultasi   = $poli_bpjs->tipe_konsultasi;
                    $ruangan   = $tipe_konsultasi->ruangan;
                }
            }
            $tipe_konsultasi_id = $ruangan->tipe_konsultasi->id;
        }

        if (
            is_null( $id )
        ) {
            if (!is_null( $staf_id )) {
                $petugas = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                    ->where('staf_id', $staf_id)
                    ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                    ->first();
                $id = $petugas->ruangan_id;
            } else {
                Log::info('=======================');
                Log::info('tipe_konsultasi KEDUA');
                Log::info($tipe_konsultasi_id);
                Log::info('=======================');
                $id = $ruangan->id;
            }
        }

        $antrian                           = new Antrian;
        $antrian->tenant_id                = 1 ;
        $antrian->nomor_bpjs               = $this->input_nomor_bpjs;
        $antrian->tipe_konsultasi_id       = $tipe_konsultasi_id;
        $antrian->staf_id                  = $this->input_staf_id;
        $antrian->ruangan_id               = $id ;
		$antrian->antriable_id             = $antrian->id;
		$antrian->registrasi_pembayaran_id = $this->input_registrasi_pembayaran_id;
		$antrian->antriable_type           = 'App\\Models\\Antrian';
		$antrian->save();

		$apc                     = new AntrianPoliController;
		$apc->updateJumlahAntrian(false, null);
		return $antrian;
	}
	public function antrian($id){
		$antrian = $this->antrianPost( $id );
		/* return $antrian; */
		return redirect('fasilitas/antrian_pasien')
			->withPrint($antrian->id);
	}
	public function antrianAjax($id){
		$antrian       = $this->antrianPost( $id );
		$nomor_antrian = $antrian->jenis_antrian->prefix . $antrian->nomor;
		$jenis_antrian = ucwords( $antrian->jenis_antrian->jenis_antrian );
		$timestamp = date('Y-m-d H:i:s');
		return compact('nomor_antrian', 'jenis_antrian', 'timestamp' );
	}

	public function listAntrian(){
        $antrians = Antrian::with('jenis_antrian')->where('antriable_type', 'App\\Models\\Antrian')
                                                  ->orderBy('sudah_hadir_di_klinik', 'desc')
                                                  ->get();
		return view('fasilitas.list_antrian', compact(
			'antrians'
		));
	}
	public function prosesAntrian($id){
		$antrian     = Antrian::with('jenis_antrian.poli_antrian.poli')->where('id', $id )->first();
		$nama_pasien          = '';
		$pasien_id            = '';
		$tanggal_lahir_pasien = '';
		$asuransi_id          = '';
		$nama_asuransi        = '';
		$image                = '';
		$prolanis_dm          = '';
		$prolanis_ht          = '';

		try {
			$pasien               = Pasien::where('nomor_asuransi_bpjs', $antrian->nomor_bpjs)->firstOrFail();
			$nama_pasien          = $pasien->nama;
			$pasien_id            = $pasien->id;
			$tanggal_lahir_pasien = $pasien->tanggal_lahir;
			$asuransi_id          = $pasien->asuransi_id;
			$nama_asuransi        = $pasien->nama_asuransi;
			$image                = $pasien->image;
			$prolanis_dm          = $pasien->prolanis_dm;
			$prolanis_ht          = $pasien->prolanis_ht;
		} catch (\Exception $e) {

		}

		$p                                                    = new PasiensController;
		$polis[null]                                          = '-Pilih Poli-';
		foreach ($antrian->jenis_antrian->poli_antrian as $k => $poli) {
			$polis[ $poli->poli_id ]                          = $poli->poli->poli;
		}

		$p->dataIndexPasien['poli']                      = $polis;
		$p->dataIndexPasien['antrian']                   = $antrian;
		$p->dataIndexPasien['nama_pasien_bpjs']          = $nama_pasien;
		$p->dataIndexPasien['pasien_id_bpjs']            = $pasien_id;
		$p->dataIndexPasien['tanggal_lahir_pasien_bpjs'] = $tanggal_lahir_pasien;
		$p->dataIndexPasien['asuransi_id_bpjs']          = $asuransi_id;
		$p->dataIndexPasien['nama_asuransi_bpjs']        = $nama_asuransi;
		$p->dataIndexPasien['image_bpjs']                = $image;
		$p->dataIndexPasien['prolanis_dm_bpjs']          = $prolanis_dm;
		$p->dataIndexPasien['prolanis_ht_bpjs']          = $prolanis_ht;
		return $p->index();
	}
	public function antrianPoliPost($id, Request $request ){
		$apc = new AntrianPoliController;
		$apc->input_antrian_id   = $id;
		return $apc->store($request);
	}
	public function createPasien($id){
		$ps          = new PasiensController;
		$polis[null] = ' - Pilih Poli - ';
		$antrian     = Antrian::find( $id );
		foreach ($antrian->jenis_antrian->poli_antrian as $poli) {
			$polis[ $poli->poli_id ] = $poli->poli->poli;
		}
		$ps->dataCreatePasien['poli']    = $polis;
		$ps->dataCreatePasien['antrian'] = $antrian;
		return $ps->create();
	}
	public function storePasien(Request $request, $id){
		$pc = new PasiensController;
		$pc->input_antrian_id = $id;
		return $pc->store($request);
	}
	public function getTambahAntrian($id){
		$antrian       = $this->antrianPost( $id );
		$nomor_antrian = $antrian->jenis_antrian->prefix . $antrian->nomor;
		$jenis_antrian = ucwords( $antrian->jenis_antrian->jenis_antrian );

		$pesan         = Yoga::suksesFlash(
			'Antrian baru <strong> ' . $nomor_antrian . '</strong> ke ' . $jenis_antrian . '	Berhasil ditambahkan'
		);

		return redirect()->back()->withPesan($pesan);
	}

    private function kodeUnik()
    {
        $kode_unik = substr(str_shuffle(MD5(microtime())), 0, 5);
        while (Antrian::where('created_at', 'like', date('Y-m-d') . '%')->where('kode_unik', $kode_unik)->exists()) {
            $kode_unik = substr(str_shuffle(MD5(microtime())), 0, 5);
        }
        return $kode_unik;
    }
}
