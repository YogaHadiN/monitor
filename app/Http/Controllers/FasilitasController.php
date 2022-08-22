<?php

namespace App\Http\Controllers;

use Input;

use DB;
use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\PasiensController;
use App\Http\Controllers\AntrianPolisController;
use App\Http\Controllers\AntriansController;
use App\Http\Controllers\AntrianPeriksasController;
use App\Http\Requests;
use App\Models\Fasilitas;
use App\Models\Pasien;
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

	public function __construct(){
        $this->middleware('redirectBackIfIdAntrianNotFound', ['only' => ['prosesAntrian']]);
		$this->input_nomor_bpjs = Input::get('nomor_bpjs');
		$this->middleware('prolanisFlagging', ['only' => [
			 'postAntrianPoli'
		]]);
	}
	
	public function antrianPost($id){

        $antrian                   = new Antrian;
        $antrian->nomor            = Antrian::nomorAntrian() ;
        $antrian->nomor_bpjs       = $this->input_nomor_bpjs;
        $antrian->jenis_antrian_id = $id ;
        $antrian->save();
		$antrian->antriable_id   = $antrian->id;
		$antrian->antriable_type = 'App\\Models\\Antrian';
		$antrian->save();

		$apc                     = new AntrianPolisController;
		$apc->updateJumlahAntrian(false);
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
		$antrians = Antrian::with('jenis_antrian')->where('antriable_type', 'App\\Models\\Antrian')->get();
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
		$apc = new AntrianPolisController;
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
    /**
     * undocumented function
     *
     * @return void
     */
    public static function nomorAntrian()
    {
		$antrians = Antrian::with('jenis_antrian')->where('created_at', 'like', date('Y-m-d') . '%')
							->where('jenis_antrian_id',$id)
							->orderBy('nomor', 'desc')
							->first();

		if ( is_null( $antrians ) ) {
            return 1;

		} else {
			return $antrians->nomor + 1;
		}
    }
    
}

