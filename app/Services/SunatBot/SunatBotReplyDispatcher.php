<?php

namespace App\Services\SunatBot;

use App\Models\Message;
use App\Services\WatzapService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SunatBotReplyDispatcher
{
    public function __construct(private WatzapService $watzap) {}

    /**
     * Send a list of bot bubbles to a single phone via WatZap.
     *
     * Acquires a per-phone reply lock so a parallel dispatch cannot
     * interleave bubbles. Bubbles are sent back-to-back with no delay,
     * except after a media bubble we wait sunatbot.media_settle_seconds
     * so the image/video lands on the recipient's WhatsApp before the
     * next bubble overtakes it (WatZap's HTTP 200 only confirms upload
     * accepted, not delivery).
     *
     * @param array $replies array of ['text' => string, 'media' => ?string]
     * @return bool true if dispatch ran, false if the lock could not be acquired in time
     */
    public function dispatch(string $phone, array $replies, bool $imageBotEnabled): bool
    {
        if ($replies === []) {
            return true;
        }

        $lock     = Cache::lock('sunatbot:reply:' . $phone, 60);
        $lockHeld = false;
        try {
            $lock->block((int) config('sunatbot.reply_lock_wait_seconds', 25));
            $lockHeld = true;

            $mediaBase     = rtrim((string) config('sunatbot.media_base_url', ''), '/');
            $mediaSettle   = max(0, (int) config('sunatbot.media_settle_seconds', 5));
            $prevSentMedia = false;

            foreach ($replies as $reply) {
                if ($prevSentMedia && $mediaSettle > 0) {
                    sleep($mediaSettle);
                }

                $text  = (string) ($reply['text'] ?? '');
                $media = $reply['media'] ?? null;

                $ext = is_string($media) && $media !== ''
                    ? strtolower(pathinfo($media, PATHINFO_EXTENSION))
                    : '';
                $mediaType = null;
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
                    $mediaType = 'image';
                } elseif (in_array($ext, ['mp4','mov','webm','3gp'], true)) {
                    $mediaType = 'video';
                }

                $canSendMedia = $imageBotEnabled && $mediaBase !== '' && $mediaType !== null;
                $prevSentMedia = false;

                if ($canSendMedia) {
                    $url = $mediaBase . '/' . ltrim((string) $media, '/');
                    try {
                        $result = $mediaType === 'image'
                            ? $this->watzap->sendImage($phone, $url, $text)
                            : $this->watzap->sendVideo($phone, $url, $text);
                        if (empty($result['ok'])) {
                            Log::error('SUNAT_BOT_MEDIA_FAIL', $result + ['url' => $url, 'type' => $mediaType]);
                            if ($text !== '') {
                                $this->watzap->sendText($phone, $text);
                                $this->logOutgoing($phone, $text, null);
                            }
                        } else {
                            $prevSentMedia = true;
                            $this->logOutgoing($phone, $text, $url);
                        }
                    } catch (\Throwable $mediaErr) {
                        Log::error('SUNAT_BOT_MEDIA_EXCEPTION', [
                            'err'  => $mediaErr->getMessage(),
                            'url'  => $url,
                            'type' => $mediaType,
                        ]);
                        if ($text !== '') {
                            $this->watzap->sendText($phone, $text);
                            $this->logOutgoing($phone, $text, null);
                        }
                    }
                } elseif ($text !== '') {
                    $this->watzap->sendText($phone, $text);
                    $this->logOutgoing($phone, $text, null);
                }
            }
            return true;
        } catch (LockTimeoutException $lockErr) {
            Log::warning('SUNAT_BOT_DISPATCH_LOCK_TIMEOUT', ['phone' => $phone]);
            return false;
        } finally {
            if ($lockHeld) {
                $lock->release();
            }
        }
    }

    /**
     * Persist a SunatBot outgoing bubble to messages so the conversation
     * surfaces in the same chat history UI as legacy autoReply traffic.
     * sending=1 marks it as bot/staff outbound; staf_id stays null because
     * the bot has no human author.
     */
    private function logOutgoing(string $phone, string $text, ?string $imageUrl): void
    {
        try {
            Message::create([
                'no_telp'        => $phone,
                'message'        => $text,
                'image_url'      => $imageUrl,
                'tanggal'        => date('Y-m-d H:i:s'),
                'sending'        => 1,
                'sudah_dibalas'  => 1,
                'sudah_diproses' => 1,
                'tenant_id'      => 1,
                'touched'        => 1,
                'staf_id'        => null,
                'flagged_intent' => 'sunat_bot',
            ]);
        } catch (\Throwable $e) {
            Log::warning('SUNAT_BOT_MESSAGE_LOG_FAIL', [
                'phone' => $phone,
                'err'   => $e->getMessage(),
            ]);
        }
    }
}
