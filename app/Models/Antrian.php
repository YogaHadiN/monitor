<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\AntrianNumberService;
use App\Traits\BelongsToTenant;
use App\Models\Asuransi;
use App\Models\Ruangan;
use App\Models\DeletedAntrian;
use App\Models\PetugasPemeriksa;
use App\Models\WhatsappRegistration;
use DB;
use Log;
use Carbon\Carbon;
class Antrian extends Model
{
    use BelongsToTenant,HasFactory;
    public static function boot(){
        parent::boot();

        static::creating(function (Antrian $antrian) {
            // Pastikan field ini sudah ada nilainya sebelum nomor di-generate
            $tenantId  = (int) ($antrian->tenant_id ?? 1);
            $ruanganId = (int) $antrian->ruangan_id;

            // Gunakan tanggal lokal operasional
            $tanggal = Carbon::now('Asia/Jakarta')->toDateString();

            // Ambil nomor baru yang dijamin unik per (tenant, ruangan, tanggal)
            $antrian->nomor = app(AntrianNumberService::class)->next($tenantId, $ruanganId, $tanggal);

        });

       self::created(function($antrian){
            $existing_antrians = Antrian::where('antriable_type', 'App\Models\Antrian')
                ->where('created_at', 'like' , date('Y-m-d') . '%' )
                ->where('id', 'not like' , $antrian->id )
                ->get();
            $existing_antrian_ids = [];
            foreach ($existing_antrians as $ant) {
                $existing_antrian_ids[] = $ant->id;
            }
            $antrian->existing_antrian_ids = json_encode( $existing_antrian_ids );
            $antrian->jam_pasien_mulai_mengantri = date('Y-m-d H:i:s') ;
            $antrian->save();

            //update antrian supaya sudah diantrikan
            $petugas_pemeriksa = PetugasPemeriksa::where('tanggal', $antrian->created_at->format('Y-m-d'))
                                                    ->where('staf_id', $antrian->staf_id)
                                                    ->where('tipe_konsultasi_id', $antrian->tipe_konsultasi_id)
                                                    ->where('ruangan_id', $antrian->ruangan_id)
                                                    ->first();
            if (
                !is_null( $petugas_pemeriksa ) &&
                strtotime( $petugas_pemeriksa->jam_mulai ) >= strtotime( $antrian->created_at->format("H:i:s") )
            ) {
                $petugas_pemeriksa->jam_mulai = $antrian->created_at->format("H:i:s") ;
                $petugas_pemeriksa->save();
            }
        });
        self::deleting(function($model){
            // id antrian terakhir di-update (kode Anda)
            $last_updated_antrian_id = Antrian::orderBy('updated_at','DESC')->value('id');
            $model->deleting_antrian_id = $last_updated_antrian_id;

            // <<<<< TANGKAP SUMBER DELETE (controller, file, line, route, dll)
            $model->delete_source = self::buildDeleteSourceString($model);

            // simpan perubahan ke instance (Anda memang sudah melakukan save di sini)
            $model->save();
        });

        self::deleted(function($model){
            // Hapus relasi terkait (kode Anda)
            WhatsappRegistration::where('antrian_id', $model->id)->delete();

            // <<<<< MASUKKAN delete_source KE salah satu kolom di deleted_antrians
            DeletedAntrian::create([
                'timestamp_antrian_dibuat'                      => $model->created_at,
                'ruangan_id'                                    => $model->ruangan_id,
                'url'                                           => $model->url,
                'deleting_antrian_id'                           => $model->deleting_antrian_id,
                'nomor'                                         => $model->nomor,
                'antrian_id'                                    => $model->id,
                'antriable_id'                                  => $model->antriable_id,
                'antriable_type'                                => $model->antriable_type,
                'dipanggil'                                     => $model->dipanggil,
                'no_telp'                                       => $model->no_telp,
                'nama'                                          => $model->nama,
                'tanggal_lahir'                                 => $model->tanggal_lahir,
                'kode_unik'                                     => $model->kode_unik,
                'registrasi_pembayaran_id'                      => $model->registrasi_pembayaran_id,
                'nomor_bpjs'                                    => $model->nomor_bpjs,
                'satisfaction_index'                            => $model->satisfaction_index,
                'complaint'                                     => $model->complaint,
                'register_previously_saved_patient'             => $model->register_previously_saved_patient,
                'pasien_id'                                     => $model->pasien_id,
                'recovery_index_id'                             => $model->recovery_index_id,
                'informasi_terapi_gagal'                        => $model->informasi_terapi_gagal,
                'menunggu'                                      => $model->menunggu,
                'notifikasi_panggilan_aktif'                    => $model->notifikasi_panggilan_aktif,
                'alamat'                                        => $model->alamat,
                'notifikasi_resep_terkirim'                     => $model->notifikasi_resep_terkirim,
                'jam_panggil_pasien_di_pendaftaran'             => $model->jam_panggil_pasien_di_pendaftaran,
                'jam_selesai_didaftarkan_dan_status_didapatkan' => $model->jam_selesai_didaftarkan_dan_status_didapatkan,
                'jam_mulai_pemeriksaan_fisik'                   => $model->jam_mulai_pemeriksaan_fisik,
                'jam_selesai_pemeriksaan_fisik'                 => $model->jam_selesai_pemeriksaan_fisik,
                'kartu_asuransi_image'                          => $model->kartu_asuransi_image,
                'existing_antrian_ids'                          => $model->existing_antrian_ids,
                'complain_id'                                   => $model->complain_id,
                'qr_code_path_s3'                               => $model->qr_code_path_s3,
                'reservasi_online'                              => $model->reservasi_online,
                'sudah_hadir_di_klinik'                         => $model->sudah_hadir_di_klinik,
                'data_bpjs_cocok'                               => $model->data_bpjs_cocok,
                'jam_pasien_mulai_mengantri'                    => $model->jam_pasien_mulai_mengantri,
                'customer_satisfaction_survey_sent'             => $model->customer_satisfaction_survey_sent,
                'terakhir_dipanggil'                            => $model->terakhir_dipanggil,
                'dibatalkan_pasien'                             => $model->dibatalkan_pasien,

                // <<<<< tambahkan kolom baru ini
                'delete_source'                                 => $model->delete_source ?? null,
            ]);
        });
    }

    protected $guarded = ['nomor'];
	protected $casts = [
		'tanggal_lahir' => 'datetime'
	];

	public function whatsapp_registration(){
		return $this->hasOne(WhatsappRegistration::class);
	}

    public function complain(){
        return $this->belongsTo(Complain::class);
    }

    public function registrasi_pembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }

	public function antriable(){
		return $this->morphTo();
	}

	public static function nomorAntrianTerakhir($ruangan_id){
        return Antrian::whereBetween('created_at',  [Carbon::now()->startOfDay(), Carbon::now()])
                ->where('ruangan_id', $ruangan_id)
                ->orderBy('id', 'desc')
                ->first()->nomor;
	}
	public function getNomorAntrianAttribute(){
		return $this->ruangan->prefix_antrian . $this->nomor;
	}
	public function getJenisAntrianIdAttribute($value){
		if ( is_null($value) ) {
			return '6';
		}
		return $value;
	}

	public function getIsTodayAttribute(){
		return $this->created_at->format('Y-m-d') == date('Y-m-d');
	}
    public function getAsuransiIdByRegistrasiPembayaranIdAttribute(){
        $registrasi_pembayaran_id = $this->registrasi_pembayaran_id;
        if ( $registrasi_pembayaran_id == 1 ) {
            return Asuransi::BiayaPribadi()->id;
        } else if( $registrasi_pembayaran_id == 2 ) {
            return Asuransi::BPJS()->id;
        } else{
            return null;
        }
    }
    public function pasien(){
        return $this->belongsTo(Pasien::class);
    }
    public function getRegistrasiSebelumnyaAttribute(){
        $no_telp = $this->no_telp;
        $query  = "SELECT ";
        $query .= "psn.nama as nama_pasien, ";
        $query .= "psn.tanggal_lahir as tanggal_lahir, ";
        $query .= "psn.id as id_pasien ";
        $query .= "FROM antrians as ant ";
        $query .= "JOIN periksas as prx on prx.id = ant.antriable_id and antriable_type = 'App\\\Models\\\Periksa' ";
        $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
        $query .= "WHERE ant.no_telp = '{$no_telp}' ";
        $query .= "and trim(ant.no_telp) not like '' ";
        $query .= "and ant.no_telp is not null ";
        $query .= "group by psn.id";
        return DB::select($query);
    }
    public function getSisaAntrianAttribute(){
        $petugas_pemeriksa = PetugasPemeriksa::whereDate('tanggal', $this->created_at)
                                            ->where('ruangan_id', $this->ruangan_id)
                                            ->where('tipe_konsultasi_id', $this->tipe_konsultasi_id)
                                            ->first();
        return is_null( $petugas_pemeriksa )? 0 : $petugas_pemeriksa->sisa_antrian;
    }

    public function ruangan(){
        return $this->belongsTo(Ruangan::class);
    }
    public function tipe_konsultasi(){
        return $this->belongsTo(TipeKonsultasi::class);
    }
    public function petugas_pemeriksa(){
        return $this->belongsTo(PetugasPemeriksa::class);
    }
    public function staf(){
        return $this->belongsTo(Staf::class);
    }

    public function getNomorAntrianDipanggilAttribute(){
        return !is_null( $this->antrian_dipanggil )? $this->antrian_dipanggil->nomor_antrian : null;
    }

    public function getAntrianDipanggilAttribute(){
        if (
            $this->antriable_type == 'App\Models\Antrian' ||
            $this->antriable_type == 'App\Models\AntrianPoli' ||
            $this->antriable_type == 'App\Models\AntrianPeriksa'
        ) {
            $ruangan_id = $this->ruangan_id;
            $antriable_type = $this->antriable_type;
            $antriable_id = $this->antriable_id;
            if ( $antriable_type == 'App\Models\AntrianPeriksa' ) {
                $antrian_periksa = AntrianPeriksa::find( $antriable_id );
                $ruangan_id = $antrian_periksa->ruangan_id;
            }
            $query  = "SELECT ant.id as antrian_id ";
            $query .= "FROM antrian_periksas as apx ";
            $query .= "JOIN antrians as ant on ant.antriable_id = apx.id and ant.antriable_type = 'App\\\Models\\\AntrianPeriksa' ";
            $query .= "WHERE apx.tenant_id=". session()->get('tenant_id') . " ";
            $query .= "AND apx.ruangan_id = $ruangan_id ";
            $query .= "ORDER BY ant.id asc ";
            $query .= "limit 1";
            $data = DB::select($query);
            if (count( $data )) {
                $antrian_id_terpanggil = $data[0]->antrian_id;
                $nomor_antrian = Antrian::find( $antrian_id_terpanggil );
                return $nomor_antrian;
            } else {
                Log::info(229);
                return Ruangan::find( $ruangan_id )->antrian;
            }
        }
    }

    private static function buildDeleteSourceString(self $model): string
    {
        $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60);
        $caller = self::firstAppCaller($trace);

        $routeName   = '-';
        $routeAction = '-';
        $url         = '-';
        $ip          = '-';
        $userId      = '-';

        if (!app()->runningInConsole() && app()->bound('request')) {
            $route       = optional(request()->route());
            $routeAction = method_exists($route, 'getActionName') ? ($route->getActionName() ?? '-') : '-';
            $routeName   = method_exists($route, 'getName') ? ($route->getName() ?? '-') : '-';
            $url         = request()->fullUrl() ?? '-';
            $ip          = request()->ip() ?? '-';
            $userId      = optional(auth()->user())->id ?? '-';
        }

        return sprintf(
            '%s@%s in %s:%s | route=%s | action=%s | url=%s | user=%s | ip=%s',
            $caller['class'] ?? '-',
            $caller['function'] ?? '-',
            $caller['file'] ?? '-',
            $caller['line'] ?? '-',
            $routeName,
            $routeAction,
            $url,
            $userId,
            $ip
        );
    }
}
