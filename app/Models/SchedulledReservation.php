<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class SchedulledReservation extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    protected $casts = [
        'deleted_meta' => 'array',
        'deleted_at'   => 'datetime',
    ];

    protected $guarded = [];


    protected static function booted()
    {
        static::deleting(function (SchedulledReservation $reservasi) {

            if (method_exists($reservasi, 'isForceDeleting') && $reservasi->isForceDeleting()) {
                return;
            }

            $action = null;
            try {
                $route = request()->route();
                $action = $route ? $route->getActionName() : null;
            } catch (\Throwable $e) {}

            $reservasi->deleted_by  = Auth::id();
            $reservasi->deleted_via = $action ?: (app()->runningInConsole() ? 'console' : 'unknown');

            // meta aman (optional)
            $meta = null;
            try {
                if (!app()->runningInConsole()) {
                    $meta = [
                        'ip'  => request()->ip(),
                        'url' => request()->fullUrl(),
                    ];
                }
            } catch (\Throwable $e) {
                $meta = null;
            }

            $reservasi->deleted_meta = $meta;
        });

        static::deleted(function (SchedulledReservation $reservasi) {
            $second_int = (int) date('s');

            if ($reservasi->schedulled_booking && $second_int < 30) {
                Artisan::call('reservasi:send-waitlist-inquiry');
            }
        });
    }


    public function whatsappBot(){
        return $this->belongsTo(WhatsappBot::class);
    }
    public function registrasi_pembayaran(){
        return $this->belongsTo(RegistrasiPembayaran::class);
    }
    public function pasien(){
        return $this->belongsTo(Pasien::class);
    }
    public function tipe_konsultasi(){
        return $this->belongsTo(TipeKonsultasi::class);
    }
    public function staf(){
        return $this->belongsTo(Staf::class);
    }
    public function petugas_pemeriksa(){
        return $this->belongsTo(PetugasPemeriksa::class);
    }
}
