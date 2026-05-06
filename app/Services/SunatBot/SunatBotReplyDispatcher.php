<?php

namespace App\Services\SunatBot;

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
     * interleave bubbles. Pauses between bubbles using
     * sunatbot.reply_delay_seconds and pauses longer after a media
     * bubble (sunatbot.media_settle_seconds) so the image/video has
     * time to land in WhatsApp before the next bubble overtakes it.
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
            $replyDelay    = max(0, (int) config('sunatbot.reply_delay_seconds', 0));
            $mediaSettle   = max(0, (int) config('sunatbot.media_settle_seconds', 5));
            $firstReply    = true;
            $prevSentMedia = false;

            foreach ($replies as $reply) {
                if (!$firstReply) {
                    $gap = $prevSentMedia ? max($mediaSettle, $replyDelay) : $replyDelay;
                    if ($gap > 0) sleep($gap);
                }
                $firstReply = false;

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
                            }
                        } else {
                            $prevSentMedia = true;
                        }
                    } catch (\Throwable $mediaErr) {
                        Log::error('SUNAT_BOT_MEDIA_EXCEPTION', [
                            'err'  => $mediaErr->getMessage(),
                            'url'  => $url,
                            'type' => $mediaType,
                        ]);
                        if ($text !== '') {
                            $this->watzap->sendText($phone, $text);
                        }
                    }
                } elseif ($text !== '') {
                    $this->watzap->sendText($phone, $text);
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
}
