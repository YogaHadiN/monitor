<?php

namespace App\Console\Commands;

use App\Models\BotSession;
use App\Services\SunatBot\SunatBotAgent;
use Illuminate\Console\Command;

/**
 * Manual REPL untuk debug SunatBotAgent tanpa kirim ke gowa.
 *
 *   php artisan sunatbot:agent-test 6281381912803 "halo, ada promo?"
 *   php artisan sunatbot:agent-test 6281381912803 --reset  (clear history)
 *
 * Tidak menulis ke gowa_outbound_messages. Tidak ada side effect ke
 * booking / harga flow — agent diisolasi, signal di-print saja.
 */
class SunatbotAgentTest extends Command
{
    protected $signature   = 'sunatbot:agent-test {phone : nomor E.164 tanpa "+"} {message? : pesan inbound} {--reset : hapus agent_history sebelum proses}';
    protected $description = 'Debug SunatBotAgent: simulasi inbound, print tool calls + reply bubbles';

    public function handle(): int
    {
        $phone   = (string) $this->argument('phone');
        $message = (string) $this->argument('message');
        $reset   = (bool) $this->option('reset');

        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') {
            $this->error('Phone wajib diisi (E.164 tanpa +).');
            return 1;
        }

        $session = BotSession::firstOrCreate(['no_telp' => $phone]);
        if ($reset) {
            $session->agent_history = null;
            $session->save();
            $this->info("agent_history di-clear untuk $phone.");
            if ($message === '') return 0;
        }

        if ($message === '') {
            $this->error('Message wajib diisi (atau pakai --reset saja).');
            return 1;
        }

        $this->line("→ inbound from $phone: " . $message);
        $this->line(str_repeat('-', 60));

        $agent  = app(SunatBotAgent::class);
        $start  = microtime(true);
        $result = $agent->reply($session, $message);
        $ms     = (int) round((microtime(true) - $start) * 1000);

        $this->line("← agent result (took {$ms}ms):");
        $this->line("  handled : " . ($result['handled'] ? 'true' : 'false'));
        $this->line("  signal  : " . ($result['signal'] ?? '(none)'));
        $this->line("  escalate: " . ($result['escalate'] ? 'true' : 'false'));
        $this->line("  bubbles : " . count($result['replies']));

        foreach ($result['replies'] as $i => $b) {
            $n   = $i + 1;
            $txt = (string) ($b['text'] ?? '');
            $med = (string) ($b['media'] ?? '');
            $this->line(str_repeat('-', 60));
            $this->line("  bubble #$n" . ($med !== '' ? " [media: $med]" : ''));
            if ($txt !== '') {
                foreach (explode("\n", $txt) as $line) {
                    $this->line("    " . $line);
                }
            }
        }

        return 0;
    }
}
