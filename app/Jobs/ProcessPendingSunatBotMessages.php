<?php

namespace App\Jobs;

use App\Models\BotPendingBuffer;
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
        $combined = null;

        DB::transaction(function () use (&$combined) {
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

        if (empty($result['handled']) || empty($result['replies'])) {
            return;
        }

        // Tenant flag toggles whether media bubbles are sent at all. The
        // controller resolves tenant_id 1 unconditionally; mirror that
        // here so the dispatcher behaves identically to the old sync flow.
        $tenant          = Tenant::find(1);
        $imageBotEnabled = $tenant && (bool) ($tenant->image_bot_enabled ?? false);

        $dispatcher->dispatch($this->phone, $result['replies'], $imageBotEnabled);
    }
}
