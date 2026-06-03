<?php

namespace App\Console\Commands;

use App\Models\BotIntent;
use Illuminate\Console\Command;

/**
 * Toggle versi promo dari paket harga sunat bot.
 *   php artisan sunatbot:promo on   → aktifkan template promo
 *   php artisan sunatbot:promo off  → kembali ke template normal
 *   php artisan sunatbot:promo      → tampilkan status saat ini
 *
 * Internal: ubah kolom `active` pada row bot_intents
 * intent='quote_harga_paket_promo'. SunatBotEngine memilih intent
 * promo bila aktif, fallback ke `quote_harga_paket` normal bila off.
 */
class SunatbotPromoToggle extends Command
{
    protected $signature   = 'sunatbot:promo {state? : on|off (kosong = tampilkan status)}';
    protected $description = 'Toggle versi promo paket harga sunat bot';

    public function handle(): int
    {
        $row = BotIntent::where('intent', 'quote_harga_paket_promo')->first();
        if (!$row) {
            $this->error('Intent quote_harga_paket_promo belum ada di bot_intents. Jalankan migrasi atika dulu.');
            return 1;
        }

        $state = strtolower((string) $this->argument('state'));

        if ($state === '') {
            $this->info('Status saat ini: ' . ($row->active ? 'ON (promo aktif)' : 'OFF (harga normal)'));
            return 0;
        }

        if (!in_array($state, ['on', 'off', '1', '0'], true)) {
            $this->error('State harus "on" atau "off".');
            return 1;
        }

        $newActive = in_array($state, ['on', '1'], true) ? 1 : 0;
        $row->active = $newActive;
        $row->save();

        $this->info('Promo sekarang ' . ($newActive ? 'ON' : 'OFF') . '.');
        return 0;
    }
}
