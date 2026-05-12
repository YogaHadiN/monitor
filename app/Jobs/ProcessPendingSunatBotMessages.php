<?php

namespace App\Jobs;

use App\Models\BotPendingBuffer;
use App\Models\BotSession;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\SunatBot\SunatBotEngine;
use App\Services\SunatBot\SunatBotReplyDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPendingSunatBotMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // No automatic retry — a stale buffer simply exits, and a newer
    // incoming message dispatches its own job that will pick up the
    // (now-larger) buffer.
    public int $tries = 1;

    public function __construct(
        public string $phone,
        public int $version
    ) {}

    public function handle(SunatBotEngine $engine, SunatBotReplyDispatcher $dispatcher): void
    {
        $combined        = null;
        $bufferMessages  = [];

        DB::transaction(function () use (&$combined, &$bufferMessages) {
            $buffer = BotPendingBuffer::where('phone', $this->phone)
                ->lockForUpdate()
                ->first();

            if ($buffer === null) {
                return;
            }

            // Newer message arrived after this job was scheduled — that
            // newer webhook dispatched its own job which will pick up the
            // full (extended) buffer. We bail to avoid double-processing.
            if ((int) $buffer->version !== $this->version) {
                Log::info('SUNAT_BOT_BUFFER_STALE', [
                    'phone'          => $this->phone,
                    'job_version'    => $this->version,
                    'buffer_version' => (int) $buffer->version,
                ]);
                return;
            }

            // Defensive: we already flushed this batch.
            if ($buffer->processed_at !== null) {
                return;
            }

            $messages = $buffer->messages ?? [];
            if ($messages === []) {
                return;
            }

            $combined = collect($messages)
                ->pluck('text')
                ->filter(fn ($t) => trim((string) $t) !== '')
                ->implode("\n");

            $bufferMessages       = $messages;
            $buffer->processed_at = now();
            $buffer->save();
        });

        if ($combined === null || $combined === '') {
            return;
        }

        Log::info('SUNAT_BOT_BUFFER_FLUSH', [
            'phone'    => $this->phone,
            'version'  => $this->version,
            'combined' => $combined,
        ]);

        try {
            $result = $engine->handle($this->phone, $combined);
        } catch (\Throwable $e) {
            Log::error('SUNAT_BOT_BUFFER_FLUSH_FAIL', [
                'phone' => $this->phone,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        // If the engine escalated this turn, flip the inbound Message
        // rows that came in via THIS buffer flush (and only this flush)
        // back to sudah_dibalas=0 so admin sees them as pending. We use
        // the buffer's own per-bubble `at` timestamps as the lower bound
        // — id-based cutoffs would miss the trigger message when the
        // dispatcher is still emitting earlier bubbles in parallel.
        $session = BotSession::where('no_telp', $this->phone)->first();
        if ($session && $session->requires_special_handling) {
            $this->flipBufferInboundToUnread($session, $bufferMessages);
        }

        if (empty($result['handled']) || empty($result['replies'])) {
            return;
        }

        // Tenant flag toggles whether media bubbles are sent at all. The
        // controller resolves tenant_id 1 unconditionally; mirror that
        // here so the dispatcher behaves identically to the old sync flow.
        $tenant          = Tenant::find(1);
        $imageBotEnabled = $tenant && (bool) ($tenant->image_bot_enabled ?? false);

        // For escalated sessions we still send the goodbye bubble via
        // WatZap (customer needs to see "we'll forward to admin"), but
        // we DO NOT log it into messages — the admin inbox should only
        // surface the customer's unanswered question, not the bot's
        // handover prelude.
        $skipLog = $session && $session->requires_special_handling;

        $dispatcher->dispatch($this->phone, $result['replies'], $imageBotEnabled, $skipLog);
    }

    /**
     * Flip Message rows that landed in THIS buffer flush from
     * sudah_dibalas=1 back to 0. Scope = inbound sunat_bot rows for
     * this phone created at or after the earliest at-timestamp in the
     * flushed buffer. Earlier turns where the bot already replied are
     * left alone — they keep their handled status.
     */
    private function flipBufferInboundToUnread(BotSession $session, array $bufferMessages): void
    {
        if ($bufferMessages === []) {
            return;
        }

        $earliestAt = collect($bufferMessages)
            ->pluck('at')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->min();

        if ($earliestAt === null) {
            return;
        }

        $flipped = Message::where('no_telp', $this->phone)
            ->where('sending', 0)
            ->where('flagged_intent', 'sunat_bot')
            ->where('sudah_dibalas', 1)
            ->where('created_at', '>=', $earliestAt)
            ->update(['sudah_dibalas' => 0]);

        if ($flipped > 0) {
            Log::info('SUNAT_BOT_INBOUND_FLIPPED_UNREAD', [
                'phone'   => $this->phone,
                'session' => $session->id,
                'count'   => $flipped,
                'since'   => $earliestAt,
            ]);
        }
    }
}
