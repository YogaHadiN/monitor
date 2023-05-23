<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asuransi;
use App\Models\Pasien;
use App\Models\Antrian;
use Carbon\Carbon;

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
		$this->input_bukan_peserta       = Input::get('bukan_peserta');
        /* $this->middleware('nomorAntrianUnik', ['only' => ['store']]); */
    }
    public function prosesData(){
		DB::beginTransaction();
		try {
            $this->asuransi_is_bpjs = !empty( $this->input_asuransi_id ) ? Asuransi::find($this->input_asuransi_id)->tipe_asuransi_id == 5: false;
			$this->input_pasien     = Pasien::find( $this->input_pasien_id );

            $this->input_antrian = !is_null($this->input_antrian_id) ? Antrian::with('whatsapp_registration')->where( 'id',$this->input_antrian_id )->first() : null;


			//cek jika antrian sudah tidak ada, maka jangan dilanjutkan
			//
			//

			if (
				!is_null($this->input_antrian_id) &&
				is_null( $this->input_antrian )
			) {
				$pesan = gagalFlash('Antrian tidak ditemukan, mungkin tidak sengaja terhapus');
				return redirect('antrians')->withPesan($pesan);
			}

            if (
                !is_null( $this->input_antrian ) &&
                !empty( $this->input_nomor_asuransi_bpjs )
            ) {
                $this->input_pasien->nomor_asuransi_bpjs = $this->input_nomor_asuransi_bpjs;
                if ( empty(  $this->input_pasien->bpjs_image  ) ) {
                    $this->input_pasien->bpjs_image          = empty( trim( $this->input_antrian->kartu_asuransi_image ) ) ? null :  $this->input_antrian->kartu_asuransi_image ;
                }
                $this->input_pasien->save();
            }

            if (
                !is_null( $this->input_antrian ) &&
                !empty( $this->input_nomor_asuransi )
            ) {
                $this->input_pasien->nomor_asuransi = $this->input_nomor_asuransi;
                if ( empty(  $this->input_pasien->kartu_asuransi_image  ) ) {
                    $this->input_pasien->kartu_asuransi_image = empty( trim( $this->input_antrian->kartu_asuransi_image ) ) ? null :  $this->input_antrian->kartu_asuransi_image ;
                }
                $this->input_pasien->save();
            }
			/* Jika pasien belum difoto, arahkan ke search pasien */
			if (
				empty($this->input_pasien->image)
			) {
				$pesan = gagalFlash('Gambar <strong>Foto pasien</strong> harus dimasukkan terlebih dahulu');
				return redirect('pasiens/' . $this->input_pasien_id . '/edit')
					->withPesan($pesan);
			}
			/* Jika pasien memiliki KTP dan nomor ktp belum diisi */
			if (
				!is_null( $this->input_pasien->ktp_image )  	// jika ktp_image tidak null
				&& !empty( $this->input_pasien->ktp_image ) 	// jika ktp_image tidak empty
				&& (empty($this->input_pasien->nomor_ktp) || is_null($this->input_pasien->nomor_ktp))  // dan nomor ktp masih kogong
			) {
				$pesan = gagalFlash('Nomor KTP harus diisi terlebih dahulu sebelum dilanjutkan, gunakan foto KTP'); // Nomor KTP harus diisi
				return redirect('pasiens/' . $this->pasien_id . '/edit')->withPesan($pesan);
			}

			/* Jika pasien berusia kurang dari 15 tahun dan terakhir di update lebih dari satu tahun yang lalu, update foto pasien */
			if (
				$this->input_pasien->usia < 15 && // jika usia pasien kurang dari 15 tahun
				Carbon::now()->subYears(1)->greaterThan( $this->input_pasien->updated_at )
			) {
				$pesan = gagalFlash('Gambar <strong>Foto pasien</strong> harus di update dengan yang terbaru karena sudah lewat 1 tahun');
				return redirect('pasiens/' . $this->pasien_id . '/edit')
					->withPesan($pesan);
			}

			/* Jika pasien berusia lebih dari 15 tahun dan terakhir di update lebih dari 5 tahun yang lalu, update foto pasien */
			if (
				$this->input_pasien->usia > 15 && // jika usia pasien lebih dari 15 tahun
				Carbon::now()->subYears(5)->greaterThan( $this->input_pasien->updated_at ) // dan lebih dari 5 tahun yang lalu diupdate
			) {
				$pesan = gagalFlash('Gambar <strong>Foto pasien</strong> harus di update dengan yang terbaru karena sudah lewat 5 tahun');
				return redirect('pasiens/' . $this->pasien_id . '/edit')
					->withPesan($pesan);
			}

			/* Jika pasien BPJS dan usia lebih dari 18 tahun namun tidak membawa KTP */
			if (
				$this->asuransi_is_bpjs && // pasien bpjs
				(empty($this->input_pasien->ktp_image) && $this->input_pasien->usia >= 18) // jika usia > 18 tahun tapi tidak bawa KTP
			) {
				$pesan = gagalFlash('Gambar <strong>gambar KTP pasien (bila DEWASA) </strong> untuk peserta asuransi harus dimasukkan terlebih dahulu');
				return redirect('pasiens/' . $this->input_pasien->id . '/edit')
					->withPesan($pesan);
			}

			/* Jika pasien BPJS dan usia lebih dari 18 tahun namun tidak membawa KTP */
			if (
				$this->asuransi_is_bpjs && // pasien bpjs
				(empty($this->input_pasien->nomor_asuransi_bpjs) && is_null($this->input_pasien->nomor_asuransi_bpjs)) // jika usia > 18 tahun tapi tidak bawa KTP
			) {
				$pesan = gagalFlash('Nomor Asuransi BPJS harus diisi');
				return redirect('pasiens/' . $this->pasien_id . '/edit')
					->withPesan($pesan);
			}

			/* Jika pasien BPJS dan tidak membawa kartu BPJS */
			if (
				$this->asuransi_is_bpjs && // pasien bpjs
				empty($this->input_pasien->bpjs_image) //jika kartu bpjs masih kosong
			) {
				$pesan = gagalFlash('Gambar <strong>Kartu BPJS</strong> untuk peserta asuransi harus dimasukkan terlebih dahulu');
				return redirect('pasiens/' . $this->input_pasien->id . '/edit')
					->withPesan($pesan);
			}

			$rules = [
				'tanggal'     => 'required',
				'asuransi_id' => 'required',
				'poli_id'     => 'required'
			];
			
			$validator = \Validator::make(Input::all(), $rules);
			
			if ($validator->fails())
			{
				return \Redirect::back()->withErrors($validator)->withInput();
			}



            //
			//jika pasien memilih poli rapid test gak usah masuk nurse station
			//

            if (!is_null( $this->input_antrian_id )) {
                $this->input_antrian->jam_selesai_didaftarkan_dan_status_didapatkan = date('H:i:s');
                $this->input_antrian->save();
            }


            $nursestation_available = \Auth::user()->tenant->nursestation_availability;
            $ap                     = null;
            if (
                (!is_null($this->input_antrian) &&( $this->input_antrian->jenis_antrian_id == 7 || $this->input_antrian->jenis_antrian_id == 8)) ||
                !\Auth::user()->tenant->nursestation_availability
            ) {
                $ap = $this->inputAntrianPeriksa();
            } else {
                $ap = $this->inputDataAntrianPoli();
            }

            $this->updateAntrianDanKirimWa($ap);
            // hapus jika ada whatsapp registration

			$this->updateJumlahAntrian(false, null);
			DB::commit();
            if ( get_class($ap) == "App\Models\AntrianPeriksa") {
                return $this->arahkanAntrianPeriksa($ap);
            } else {
                return $this->arahkanAntrianPoli($ap);
            }
		} catch (\Exception $e) {
			DB::rollback();
			throw $e;
		}
    }
}
