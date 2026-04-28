<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\WablasController;

class SendWablasMessage extends Command
{
    protected $signature = 'wablas:send
                            {phone=081381912803 : Nomor tujuan (boleh format 08xx atau 628xx)}
                            {message=halo : Isi pesan}';

    protected $description = 'Kirim pesan WhatsApp via Wablas (utility manual / smoke test)';

    public function handle(): int
    {
        $phone   = $this->normalizePhone((string) $this->argument('phone'));
        $message = (string) $this->argument('message');

        if ($phone === '') {
            $this->error('Nomor tidak valid.');
            return self::FAILURE;
        }

        if (empty(env('WABLAS_TOKEN'))) {
            $this->error('WABLAS_TOKEN belum di-set di .env');
            return self::FAILURE;
        }

        $this->line("→ kirim ke {$phone}: {$message}");

        $result = (new WablasController)->sendSingleWablas($phone, $message);

        if ($result === null || $result === false) {
            $this->error('Gagal kirim. Cek log untuk detail (WABLAS_SEND_FAILED / WABLAS_TOKEN_MISSING).');
            return self::FAILURE;
        }

        $this->info('Response Wablas:');
        $this->line($result);

        return self::SUCCESS;
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        if (str_starts_with($digits, '62')) {
            return $digits;
        }
        return '62' . $digits;
    }
}
