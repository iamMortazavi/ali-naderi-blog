<?php

namespace App\Services\Telegram;

use App\Enums\TelegramStatus;
use App\Models\Post;
use App\Models\TelegramMessage;
use App\Support\SiteSettings;
use App\Support\Url;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sends each newly published post to the Telegram channel exactly once.
 *
 * Claim first, then send: a telegram_messages row with status "sending" is inserted before the API
 * call (post_id is unique, so two overlapping runs can never both claim a post). Then:
 * - Telegram accepts → status "sent", message_id, sent_at;
 * - Telegram refuses (ok = false) → status "failed"; retried automatically up to
 *   blog.telegram.max_attempts times, at least 10 minutes apart, then left for the panel;
 * - no answer (timeout, connection error) → the row stays "sending": the message may already be in
 *   the channel, so it is never resent automatically. The panel can retry() it after checking.
 *
 * Only posts published within blog.telegram.max_age_hours are sent automatically, so importing an
 * old archive never floods the channel. Bot token and channel id come from .env (TELEGRAM_BOT_TOKEN,
 * TELEGRAM_CHAT_ID); the token never appears in an exception message, log line or notice.
 */
class TelegramPublisher
{
    public const CAPTION_LIMIT = 1024;

    public const MESSAGE_LIMIT = 4096;

    /** Minutes between automatic attempts for a post Telegram refused. */
    public const RETRY_AFTER_MINUTES = 10;

    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id'));
    }

    /**
     * Posts the scheduler sends now: live, "send to Telegram" on, language chosen in Settings →
     * Telegram, published within the age window, and never tried or refused fewer than max_attempts
     * times (last attempt over 10 minutes ago).
     *
     * @return Collection<int,Post>
     */
    public function due(): Collection
    {
        $maxAttempts = (int) config('blog.telegram.max_attempts', 3);

        return Post::query()->live()
            ->where('send_to_telegram', true)
            ->whereIn('locale', (array) SiteSettings::get('telegram.languages', ['fa']))
            ->where('published_at', '>=', now()->subHours((int) config('blog.telegram.max_age_hours', 48)))
            ->where(fn (Builder $q) => $q->whereDoesntHave('telegramMessage')
                ->orWhereHas('telegramMessage', fn (Builder $m) => $m->where('status', TelegramStatus::Failed)
                    ->where('attempts', '<', $maxAttempts)
                    ->where('updated_at', '<=', now()->subMinutes(self::RETRY_AFTER_MINUTES))))
            ->orderBy('published_at')->orderBy('id')
            ->with(['tags', 'telegramMessage'])
            ->get();
    }

    /**
     * Send every due post. A failure with one post never stops the others.
     *
     * @return list<TelegramMessage> one row per post tried in this run (with ->post loaded); its status
     *                               is sent, failed, or sending (= no answer, result unknown)
     */
    public function sendDue(int $pause = 3): array
    {
        if (! $this->configured()) {
            return [];
        }
        $rows = [];
        foreach ($this->due() as $post) {
            try {
                $row = $post->telegramMessage
                    ? $this->claimExisting($post, [TelegramStatus::Failed], fn ($q) => $q
                        ->where('attempts', '<', (int) config('blog.telegram.max_attempts', 3))
                        ->where('updated_at', '<=', now()->subMinutes(self::RETRY_AFTER_MINUTES)))
                    : $this->claimNew($post);
                if (! $row) {
                    continue; // another run has it
                }
                if ($rows && $pause > 0) {
                    sleep($pause);
                }
                $rows[] = $this->deliver($post, $row);
            } catch (Throwable $e) {
                Log::error("Telegram: post {$post->id} could not be processed: ".$this->scrub($e->getMessage()));
            }
        }

        return $rows;
    }

    /**
     * Send one post now (panel button). Also sends posts outside the age window, posts marked as
     * skipped and posts Telegram refused before.
     *
     * @throws RuntimeException with a message safe to show in the panel
     */
    public function send(Post $post): TelegramMessage
    {
        if (! $this->configured()) {
            throw new RuntimeException('The Telegram bot is not set up (TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env).');
        }
        $existing = TelegramMessage::query()->where('post_id', $post->id)->first();
        $row = match ($existing?->status) {
            null => $this->claimNew($post),
            TelegramStatus::Skipped, TelegramStatus::Failed => $this->claimExisting($post, [TelegramStatus::Skipped, TelegramStatus::Failed]),
            TelegramStatus::Sent => throw new RuntimeException('This post was already sent to Telegram.'),
            TelegramStatus::Sending => throw new RuntimeException('The last attempt to send this post got no answer from Telegram, so it may already be in the channel. Check the channel, then use "retry".'),
        };
        if (! $row) {
            throw new RuntimeException('This post is being sent to Telegram right now.');
        }
        $row = $this->deliver($post, $row);
        $post->setRelation('telegramMessage', $row);

        return match ($row->status) {
            TelegramStatus::Sent => $row,
            TelegramStatus::Sending => throw new RuntimeException('No answer from Telegram; the message may or may not be in the channel. Check the channel before retrying. '.$row->last_error),
            default => throw new RuntimeException('Telegram did not accept the message: '.$row->last_error),
        };
    }

    /** Forget a failed or unknown ("sending") attempt and send again. */
    public function retry(Post $post): TelegramMessage
    {
        TelegramMessage::query()->where('post_id', $post->id)
            ->whereIn('status', [TelegramStatus::Failed, TelegramStatus::Sending])->delete();
        $post->unsetRelation('telegramMessage');

        return $this->send($post);
    }

    /** Mark every currently due post as skipped without sending (first install, archive import). */
    public function baseline(): int
    {
        $count = 0;
        $chat = (string) config('services.telegram.chat_id');
        foreach ($this->due() as $post) {
            if ($post->telegramMessage) {
                $count += TelegramMessage::query()->where('post_id', $post->id)->where('status', TelegramStatus::Failed)
                    ->update(['status' => TelegramStatus::Skipped, 'updated_at' => now()]);

                continue;
            }
            try {
                TelegramMessage::query()->create(['post_id' => $post->id, 'chat_id' => $chat, 'status' => TelegramStatus::Skipped]);
                $count++;
            } catch (UniqueConstraintViolationException) {
                // claimed meanwhile by a send
            }
        }

        return $count;
    }

    public function compose(Post $post, int $limit): string
    {
        $locale = $post->locale;
        $title = '<b>'.e($post->title).'</b>';
        $tags = SiteSettings::get('telegram.hashtags', true)
            ? $post->tags->map(fn ($t) => self::hashtag($t->name))->filter()->unique()->map(fn ($h) => e($h))->implode(' ')
            : '';
        $link = '<a href="'.e($post->url()).'">'.e(__('site.readMore', [], $locale)).'</a>';
        $footer = e((string) SiteSettings::get("telegram.footer_{$locale}", ''));
        $tail = collect([$tags, $link, $footer])->filter()->implode("\n\n");
        $description = $post->summary(2000);
        $room = $limit - mb_strlen($title) - mb_strlen($tail) - 8;
        if (mb_strlen($description) > $room) {
            $description = rtrim(mb_substr($description, 0, max($room - 1, 0))).'…';
        }

        return collect([$title, e($description), $tail])->filter()->implode("\n\n");
    }

    /**
     * Telegram hashtags allow letters, digits and _ only; spaces, half-spaces, dashes and every
     * other character become "_". Returns '' when nothing usable is left.
     */
    public static function hashtag(string $tag): string
    {
        $tag = preg_replace('/\p{M}+/u', '', trim($tag)) ?? '';
        $tag = trim(preg_replace('/[^\p{L}\p{N}_]+/u', '_', $tag) ?? '', '_');
        $tag = preg_replace('/_{2,}/', '_', $tag) ?? '';

        return $tag === '' ? '' : '#'.$tag;
    }

    /** Private notice to the maintainer chat (TELEGRAM_NOTIFY_CHAT_ID), if set. Never throws. */
    public function notify(string $html): void
    {
        $chat = config('services.telegram.notify_chat_id');
        if (! $chat || ! config('services.telegram.bot_token')) {
            return;
        }
        try {
            $this->call('sendMessage', ['chat_id' => $chat, 'text' => $html, 'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true]]);
        } catch (Throwable $e) {
            Log::warning('Telegram notice failed: '.$this->scrub($e->getMessage()));
        }
    }

    /** Insert the "sending" row; null when the post already has one. */
    private function claimNew(Post $post): ?TelegramMessage
    {
        try {
            return TelegramMessage::query()->create([
                'post_id' => $post->id,
                'chat_id' => (string) config('services.telegram.chat_id'),
                'status' => TelegramStatus::Sending,
                'attempts' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Move an existing row back to "sending" if it is still in one of $from (a conditional update,
     * so only one process can win); null when it is not.
     *
     * @param  list<TelegramStatus>  $from
     */
    private function claimExisting(Post $post, array $from, ?callable $constraint = null): ?TelegramMessage
    {
        $query = TelegramMessage::query()->where('post_id', $post->id)->whereIn('status', $from);
        if ($constraint) {
            $constraint($query);
        }
        $claimed = $query->update([
            'status' => TelegramStatus::Sending,
            'chat_id' => (string) config('services.telegram.chat_id'),
            'updated_at' => now(),
        ]);

        return $claimed === 1 ? TelegramMessage::query()->where('post_id', $post->id)->first() : null;
    }

    /** Send a claimed post and record the outcome on its row. */
    private function deliver(Post $post, TelegramMessage $row): TelegramMessage
    {
        $attempts = $row->attempts + 1;
        try {
            $messageId = $this->transmit($post);
            $row->fill(['status' => TelegramStatus::Sent, 'message_id' => $messageId, 'sent_at' => now(),
                'attempts' => $attempts, 'last_error' => null]);
        } catch (TelegramUnknownResultException $e) {
            // The message may be in the channel: keep "sending" so it is never sent twice.
            $row->fill(['attempts' => $attempts, 'last_error' => $this->errorText($e)]);
        } catch (Throwable $e) {
            // Refused by Telegram, or failed before anything was sent.
            $row->fill(['status' => TelegramStatus::Failed, 'attempts' => $attempts, 'last_error' => $this->errorText($e)]);
        }
        $row->save();
        $row->setRelation('post', $post);

        return $row;
    }

    /** @return int|null the channel message id */
    private function transmit(Post $post): ?int
    {
        $chat = (string) config('services.telegram.chat_id');
        $cover = $post->coverUrl();
        if ($cover) {
            try {
                $result = $this->call('sendPhoto', ['chat_id' => $chat, 'photo' => Url::absolute($cover), 'parse_mode' => 'HTML',
                    'caption' => $this->compose($post, self::CAPTION_LIMIT)]);

                return isset($result['message_id']) ? (int) $result['message_id'] : null;
            } catch (TelegramApiException $e) {
                if ($e->errorCode !== 400) {
                    throw $e;
                }
                // Usually the cover cannot be fetched or is too large: send the text instead, once.
                Log::warning("Telegram: cover of post {$post->id} refused, sending text only: ".$e->getMessage());
            }
        }
        $result = $this->call('sendMessage', ['chat_id' => $chat, 'parse_mode' => 'HTML',
            'text' => $this->compose($post, self::MESSAGE_LIMIT),
            'link_preview_options' => ['url' => $post->url(), 'prefer_large_media' => true]]);

        return isset($result['message_id']) ? (int) $result['message_id'] : null;
    }

    /**
     * @throws TelegramApiException when Telegram refuses (ok = false)
     * @throws TelegramUnknownResultException when there is no usable answer
     */
    private function call(string $method, array $payload): array
    {
        $url = 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method;
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::timeout(30)->connectTimeout(10)->asJson()->post($url, $payload);
            } catch (Throwable $e) {
                // Timeout or connection error (or anything else while sending): the request may have arrived.
                throw new TelegramUnknownResultException("Telegram {$method}: no answer (".$this->scrub($e->getMessage()).')');
            }
            $body = $response->json();
            if (! is_array($body) || ! array_key_exists('ok', $body)) {
                throw new TelegramUnknownResultException("Telegram {$method}: unexpected answer (HTTP {$response->status()})");
            }
            if ($body['ok'] === true) {
                return is_array($body['result'] ?? null) ? $body['result'] : [];
            }
            $code = (int) ($body['error_code'] ?? $response->status());
            $retryAfter = (int) ($body['parameters']['retry_after'] ?? 0);
            if ($code === 429 && $retryAfter > 0 && $attempt < 3) {
                sleep(min($retryAfter, 30) + 1);

                continue;
            }
            throw new TelegramApiException($this->scrub("Telegram {$method} failed: {$code} ".($body['description'] ?? '')), $code);
        }
    }

    private function errorText(Throwable $e): string
    {
        return Str::limit($this->scrub($e->getMessage()), 490);
    }

    /** Remove the bot token from any text. */
    private function scrub(string $text): string
    {
        $token = (string) config('services.telegram.bot_token');

        return $token === '' ? $text : str_replace([$token, rawurlencode($token)], '***', $text);
    }
}
