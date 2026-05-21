<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SchedulledReservation extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    protected $casts = [
        'deleted_meta' => 'array',
        'deleted_at'   => 'datetime',
    ];

    protected $guarded = [];

    /**
     * Create dari hasil toArray() model lain (ReservasiOnline /
     * WebRegistration). Normalize created_at & updated_at ke Asia/Jakarta
     * dulu — kalau langsung lewat create(), nilai ISO 8601 UTC yang
     * diemit serializeDate() ditafsirkan apa adanya saat insert
     * sehingga created_at di DB jadi 7 jam lebih muda dari waktu asli
     * Jakarta.
     */
    public static function fromSourceArray(array $data): self
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        foreach (['created_at', 'updated_at'] as $col) {
            if (!empty($data[$col])) {
                $data[$col] = \Carbon\Carbon::parse($data[$col])
                    ->setTimezone($tz)
                    ->format('Y-m-d H:i:s');
            }
        }
        return static::create($data);
    }


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
                try {
                    Artisan::call('reservasi:send-waitlist-inquiry');
                } catch (\Symfony\Component\Console\Exception\CommandNotFoundException $e) {
                    Log::warning('reservasi:send-waitlist-inquiry skipped — not registered in this app');
                }
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
