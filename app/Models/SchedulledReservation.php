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

    protected $guarded = [];

    protected static function booted()
    {
        // Sebelum delete → isi audit
        static::deleting(function (SchedulledReservation $reservasi) {

            // kalau forceDelete, skip audit soft-delete
            if (method_exists($reservasi, 'isForceDeleting') && $reservasi->isForceDeleting()) {
                return;
            }

            $action = null;

            try {
                $route = Request::route();
                $action = $route ? $route->getActionName() : null;
            } catch (\Throwable $e) {}

            $reservasi->deleted_by  = Auth::id();
            $reservasi->deleted_via = $action ?: 'unknown';

            // optional meta (hapus kalau tidak perlu)
            $meta = null;
            if (!app()->runningInConsole()) {
                $meta = [
                    'ip'  => Request::ip(),
                    'url' => Request::fullUrl(),
                ];
            }
            $reservasi->deleted_meta = $meta;
        });

        // Setelah delete → logic kamu tetap jalan
        static::deleted(function ($reservasi) {
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
