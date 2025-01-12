<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant; 
use Carbon\Carbon;

class PetugasPemeriksaController extends Controller
{
    /**
     * @param 
     */
    public $input_nama;
    public $input_ruangan_id;
    public $input_tipe_konsultasi_id;
    public $input_tanggal;
    public $input_jam_mulai;
    public $input_jam_akhir;
    public $input_key;
    public $input_displayed_rows;
    public function __construct()
    {
        $this->input_nama               = Input::get("nama");
        $this->input_ruangan_id         = Input::get("ruangan_id");
        $this->input_tipe_konsultasi_id = Input::get("tipe_konsultasi_id");
        $this->input_tanggal            = Input::get("tanggal");
        $this->input_jam_mulai          = Input::get("jam_mulai");
        $this->input_jam_akhir          = Input::get("jam_akhir");
        $this->input_key                = Input::get("key");
        $this->input_displayed_rows     = Input::get("displayed_rows");

        $this->middleware('janganSubmitJikaSudahAdaPemeriksaDiJamTersebut', ['only' => ['store', 'update']]);
        session()->put('tenant_id', 1);
    }
    
    public function index(){
        $petugas_pemeriksas = PetugasPemeriksa::all();
        return view('petugas_pemeriksas.index', compact(
            'petugas_pemeriksas'
        ));
    }
    public function show($id){
        $petugas_pemeriksa = PetugasPemeriksa::find( $id );
        return view('petugas_pemeriksas.show', compact('petugas_pemeriksa'));
    }
    public function create(){
        return view('petugas_pemeriksas.create');
    }
    public function edit($id){
        $petugas_pemeriksa = PetugasPemeriksa::find($id);
        return view('petugas_pemeriksas.edit', compact('petugas_pemeriksa'));
    }
    public function store(Request $request){
        if ($this->valid( Input::all() )) {
            return $this->valid( Input::all() );
        }
        $petugas_pemeriksa = new PetugasPemeriksa;
        $petugas_pemeriksa = $this->processData($petugas_pemeriksa);

        $pesan = Yoga::suksesFlash('PetugasPemeriksa baru berhasil dibuat');
        return redirect('petugas_pemeriksas')->withPesan($pesan);
    }
    public function update($id, Request $request){
        if ($this->valid( Input::all() )) {
            return $this->valid( Input::all() );
        }
        $petugas_pemeriksa = PetugasPemeriksa::find($id);
        $petugas_pemeriksa = $this->processData($petugas_pemeriksa);

        $pesan = Yoga::suksesFlash('PetugasPemeriksa berhasil diupdate');
        return redirect('petugas_pemeriksas')->withPesan($pesan);
    }
    public function destroy($id){
        PetugasPemeriksa::destroy($id);
        $pesan = Yoga::suksesFlash('PetugasPemeriksa berhasil dihapus');
        return redirect('petugas_pemeriksas')->withPesan($pesan);
    }

    public function processData($petugas_pemeriksa){


        $jam_mulai = Carbon::createFromFormat('H:i', Input::get('jam_mulai'));
        $jam_akhir = Carbon::createFromFormat('H:i', Input::get('jam_akhir'));

        $menit_jam_mulai = intval($jam_mulai->copy()->format('i'));;
        $menit_jam_akhir = intval($jam_akhir->copy()->format('i'));;

        if (
            $menit_jam_mulai == 0 &&
            $menit_jam_akhir == 0
        ) {
            $jam_akhir = $jam_akhir->subMinute()->format('H:i:s');
            $jam_mulai = $jam_mulai->format('H:i:s');
        }

        $petugas_pemeriksa->staf_id    = Input::get('staf_id');
        $petugas_pemeriksa->tanggal    = convertToDatabaseFriendlyDateFormat( Input::get('tanggal') );
        $petugas_pemeriksa->ruangan_id = Input::get('ruangan_id');
        $petugas_pemeriksa->jam_mulai  = $jam_mulai;
        $petugas_pemeriksa->jam_akhir  = $jam_akhir;
        $petugas_pemeriksa->tipe_konsultasi_id  = Input::get('tipe_konsultasi_id');
        $petugas_pemeriksa->save();

        return $petugas_pemeriksa;
    }
    public function import(){
        return 'Not Yet Handled';
        // run artisan : php artisan make:import PetugasPemeriksaImport 
        // di dalam file import :
        // use App\Models\PetugasPemeriksa;
        // use Illuminate\Support\Collection;
        // use Maatwebsite\Excel\Concerns\ToCollection;
        // use Maatwebsite\Excel\Concerns\WithHeadingRow;
        // class PetugasPemeriksaImport implements ToCollection, WithHeadingRow
        // {
        // 
        //     public function collection(Collection $rows)
        //     {
        //         return $rows;
        //     }
        // }

        $rows = Excel::toArray(new PetugasPemeriksaImport, Input::file('file'))[0];
        $petugas_pemeriksas     = [];
        $timestamp = date('Y-m-d H:i:s');
        foreach ($results as $result) {
            $petugas_pemeriksas[] = [

                // Do insert here

                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ];
        }
        PetugasPemeriksa::insert($petugas_pemeriksas);
        $pesan = Yoga::suksesFlash('Import Data Berhasil');
        return redirect()->back()->withPesan($pesan);
    }
    private function valid( $data ){
        $messages = [
            'required' => ':attribute Harus Diisi',
        ];

        $rules = [
            'jam_mulai'  => 'required|date_format:H:i',
            'jam_akhir'  => 'required|date_format:H:i|after:jam_mulai',
            'staf_id'    => 'required',
            'ruangan_id' => 'required'
        ];
        $validator = \Validator::make($data, $rules, $messages);
        
        if ($validator->fails())
        {
            return \Redirect::back()->withErrors($validator)->withInput();
        }
    }
    public function cek(){
        $tipe_konsultasi_id = Input::get('tipe_konsultasi_id'); 
        $petugas_pemeriksas = PetugasPemeriksa::whereDate('tanggal', date('Y-m-d'))
                                                ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                                ->get();
        $message = null;



        //
        // jika dokter gigi ada tapi belum masuk waktu pendaftaran
        // buat pesan error pendaftaran dimulai jam sekian
        //
        if (
             $petugas_pemeriksas->count()
        ) {
            if (
                $tipe_konsultasi_id == 2 // dokter gigi
            ) {
                $jam_akhir_gigi = $petugas_pemeriksas[0]->jam_akhir;
                $jam_akhir_pendaftaran_gigi = Carbon::parse( $petugas_pemeriksas[0]->jam_akhir )->subMinutes(30)->format('H:i:s');
                if (
                    $petugas_pemeriksas[0]->jam_mulai >= date("H:i:s")
                ) {
                    $message = 'Pendaftaran dokter gigi dimulai jam ' . $petugas_pemeriksas[0]->jam_mulai;
                } else if (
                    $jam_akhir_pendaftaran_gigi <= date("H:i:s")
                ) {
                    $message = 'Pendaftaran dokter gigi telah berakhir hari ini';
                } 
            } else  {

                $petugas_pemeriksas = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                                            ->where('jam_mulai', '<=', date('H:i:s'))
                                            ->where('jam_akhir', '>=', date('H:i:s'))
                                            ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                            ->where('ruangan_id','>', 0)
                                            ->get();

                $jumlah_petugas_pemeriksas_saat_ini = $petugas_pemeriksas->count();


                //
                // JIKA PASIEN SUDAH MENUMPUK NAMUN DOKTER KEDUA BELUM DATANG
                // ANTRIKAN PASIEN UNTUK DOKTER KEDUA
                //
                if (
                    $jumlah_petugas_pemeriksas_saat_ini == 1
                ) {
                    $tipe_konsultasi = TipeKonsultasi::find( $tipe_konsultasi_id );
                    $waktu_tunggu_menit = $tipe_konsultasi->waktu_tunggu_menit;

                    $jam_mulai_akhir_antrian = Carbon::now()->addMinutes( $waktu_tunggu_menit )->format('H:i:s');
                    $petugas_pemeriksas_nanti = PetugasPemeriksa::where('tanggal', date('Y-m-d'))
                                                ->where('jam_mulai', '<=', $jam_mulai_akhir_antrian)
                                                ->where('jam_akhir', '>=', $jam_mulai_akhir_antrian)
                                                ->where('tipe_konsultasi_id', $tipe_konsultasi_id)
                                                ->get();
                    /* dd($petugas_pemeriksas_nanti); */
                    if ($petugas_pemeriksas_nanti->count() > 1) {
                        $petugas_pemeriksas = $petugas_pemeriksas_nanti;
                    }
                }


                if ( $petugas_pemeriksas->count() > 1 ) {
                    // populate ulang petugas pemeriksa
                    $repopulate = [];
                    foreach ($petugas_pemeriksas as $petugas) {
                        $repopulate[] = [
                            'data'         => $petugas,
                            'sisa_antrian' => $petugas->sisa_antrian
                        ];
                    }
                    usort($repopulate, function($a, $b) {
                        return $a['sisa_antrian'] <=> $b['sisa_antrian'];
                    });

                    $data = [];
                    foreach ($repopulate as $petugas) {
                        $data[] = $petugas['data'];
                    }
                    $petugas_pemeriksas = collect($data);
                } else if (
                    $petugas_pemeriksas->count() < 1
                ) {
                    $tipe_konsultasi = TipeKonsultasi::find( $tipe_konsultasi_id );
                    $message = 'Tidak ada petugas ' . ucwords( $tipe_konsultasi->tipe_konsultasi ) . ' yang bertugas saat ini';
                }
            }
        } else {
            $tipe_konsultasi = TipeKonsultasi::find( $tipe_konsultasi_id );
            if (is_null( $tipe_konsultasi )) {
                Log::info('===========================');
                Log::info('TIPE KONSULTASI NULL KARENA');
                Log::info(Input::all());
                Log::info('===========================');
            }
            $message = 'Tidak ada petugas ' . ucwords( $tipe_konsultasi->tipe_konsultasi ) . ' yang bertugas hari ini';
        }

        return [
            'count'              => count( $petugas_pemeriksas ),
            'petugas'            => $petugas_pemeriksas ,
            'message'            => $message,
            'tipe_konsultasi_id' => $tipe_konsultasi_id,
            'view'               => view('petugas_pemeriksas.template_antrian', compact( 'petugas_pemeriksas'))->render()
        ];
    }

    public function ajax(){
		$petugas_pemeriksas = $this->queryData();
		$count              = $this->queryData(true);
		$pages              = ceil( $count/ $this->input_displayed_rows );
        $html               = view('petugas_pemeriksas.tableIndex', compact('petugas_pemeriksas'))->render();
		$result = [
            'html'  => $html, 
            'data'  => $petugas_pemeriksas,
			'pages' => $pages,
			'key'   => $this->input_key,
			'rows'  => $count
		];
        return $result;
    }

    public function queryData($count = false){
		$pass       = $this->input_key * $this->input_displayed_rows;
        $query  = "SELECT ";
		if ($count) {
			$query .= "count(pps.id) as jumlah ";
		} else {
			$query .= "pps.id as id, ";
			$query .= "stf.nama as nama_staf, ";
			$query .= "rgn.nama as nama_ruangan, ";
			$query .= "tpk.tipe_konsultasi as tipe_konsultasi, ";
			$query .= "pps.tanggal as tanggal, ";
			$query .= "pps.jam_mulai as jam_mulai, ";
			$query .= "pps.jam_akhir as jam_akhir ";
		}
        $query .= "FROM petugas_pemeriksas as pps ";
        $query .= "JOIN stafs as stf on stf.id = pps.staf_id ";
        $query .= "JOIN ruangans as rgn on rgn.id = pps.ruangan_id ";
        $query .= "JOIN tipe_konsultasis as tpk on tpk.id = pps.tipe_konsultasi_id ";
        $query .= "WHERE pps.tenant_id=". session()->get('tenant_id') . " ";

        if ( !empty( $this->input_nama ) ) {
            $query .= "AND stf.nama like '{$this->input_nama}%' ";
        }
        if ( !empty( $this->input_ruangan_id ) ) {
            $query .= "AND pps.ruangan_id like '{$this->input_ruangan_id}%' ";
        }
        if ( !empty( $this->input_tipe_konsultasi_id ) ) {
            $query .= "AND pps.tipe_konsultasi_id like '{$this->input_tipe_konsultasi_id}%' ";
        }
        $query .= "AND pps.tanggal like '{$this->input_tanggal}%' ";
        if ( !empty( $this->input_tanggal ) ) {
        }
        if ( !empty( $this->input_jam_mulai ) ) {
            $query .= "AND pps.jam_mulai like '{$this->input_jam_mulai}%' ";
        }
        if ( !empty( $this->input_jam_akhir ) ) {
            $query .= "AND pps.jam_akhir like '{$this->input_jam_akhir}%' ";
        }
		if (!$count) {
			$query .= " LIMIT {$pass}, {$this->input_displayed_rows}";
		}
        $query_result = DB::select($query);

		if (!$count) {
            return $query_result;
        } else {
            return $query_result[0]->jumlah;
        }
    }
}
