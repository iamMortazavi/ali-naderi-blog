<?php

namespace App\Services\Telegram;

use App\Models\Post;
use App\Models\TelegramMessage;
use App\Support\SiteSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends each newly published post to the Telegram channel exactly once.
 *
 * A post is sent when it is live (published, date reached), "send to Telegram" is on, its language
 * is chosen in Settings → Telegram, and it has no row in telegram_messages yet. The row is written
 * right after Telegram accepts the message, and the unique post_id makes a second send impossible.
 * Bot token and channel id come from .env (TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID).
 */
class TelegramPublisher
{
    public const CAPTION_LIMIT = 1024;

    public const MESSAGE_LIMIT = 4096;

    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id'));
    }

    /** @return Collection<int,Post> */
    public function due(): Collection
    {
        return Post::query()->live()
            ->where('send_to_telegram', true)
            ->whereIn('locale', SiteSettings::get('telegram.languages', ['fa']))
            ->whereDoesntHave('telegramMessage')
            ->orderBy('published_at')
            ->with('tags')
            ->get();
    }

    /** @return list<Post> posts sent in this run */
    public function sendDue(int $pause = 3): array
    {
        if (! $this->configured()) {
            return [];
        }
        $sent = [];
        foreach ($this->due() as $post) {
            $this->send($post);
            $sent[] = $post;
            sleep($pause);
        }

        return $sent;
    }

    public function send(Post $post): TelegramMessage
    {
        if ($post->telegramMessage()->exists()) {
            throw new RuntimeException('This post was already sent to Telegram.');
        }
        $chat = (string) config('services.telegram.chat_id');
        $cover = $post->coverUrl();
        if ($cover && ! str_starts_with($cover, 'http')) {
            $cover = SiteSettings::url().$cover;
        }
        $result = $cover
            ? $this->call('sendPhoto', ['chat_id' => $chat, 'photo' => $cover, 'parse_mode' => 'HTML',
                'caption' => $this->compose($post, self::CAPTION_LIMIT)])
            : $this->call('sendMessage', ['chat_id' => $chat, 'parse_mode' => 'HTML',
                'text' => $this->compose($post, self::MESSAGE_LIMIT),
                'link_preview_options' => ['url' => $post->url(), 'prefer_large_media' => true]]);

        return $post->telegramMessage()->create([
            'chat_id' => $chat,
            'message_id' => $result['message_id'],
            'sent_at' => now(),
        ]);
    }

    /** Record every live post as sent without sending, so importing old posts does not flood the channel. */
    public function baseline(): int
    {
        $count = 0;
        foreach ($this->due() as $post) {
            $post->telegramMessage()->create(['chat_id' => (string) config('services.telegram.chat_id'), 'message_id' => null, 'sent_at' => now()]);
            $count++;
        }

        return $count;
    }

    public function compose(Post $post, int $limit): string
    {
        $locale = $post->locale;
        $title = '<b>'.e($post->title).'</b>';
        $tags = SiteSettings::get('telegram.hashtags', true)
            ? $post->tags->map(fn ($t) => self::hashtag($t->name))->implode(' ')
            : '';
        $link = '<a href="'.e($post->url()).'">'.($locale === 'fa' ? 'ادامهٔ مطلب' : 'Read more').'</a>';
        $footer = e((string) SiteSettings::get("telegram.footer_{$locale}", ''));
        $tail = collect([$tags, $link, $footer])->filter()->implode("\n\n");
        $description = $post->summary(2000);
        $room = $limit - mb_strlen($title) - mb_strlen($tail) - 8;
        if (mb_strlen($description) > $room) {
            $description = rtrim(mb_substr($description, 0, max($room - 1, 0))).'…';
        }

        return collect([$title, e($description), $tail])->filter()->implode("\n\n");
    }

    /** Telegram hashtags allow letters, digits and _ only; half-spaces, spaces and dashes break them. */
    public static function hashtag(string $tag): string
    {
        return '#'.trim(preg_replace('/[\s\x{200C}\-.]+/u', '_', trim($tag)), '_');
    }

    /** Private notice to the maintainer chat (TELEGRAM_NOTIFY_CHAT_ID), if set. Never throws. */
    public function notify(string $text): void
    {
        $chat = config('services.telegram.notify_chat_id');
        if (! $chat || ! config('services.telegram.bot_token')) {
            return;
        }
        try {
            $this->call('sendMessage', ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true]]);
        } catch (\Throwable $e) {
            Log::warning('Telegram notice failed: '.$e->getMessage());
        }
    }

    private function call(string $method, array $payload): array
    {
        $url = 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = Http::timeout(30)->asJson()->post($url, $payload);
            $body = $response->json() ?? [];
            if ($body['ok'] ?? false) {
                return $body['result'];
            }
            $retryAfter = $body['parameters']['retry_after'] ?? null;
            if (($body['error_code'] ?? null) === 429 && $retryAfter && $attempt < 3) {
                sleep($retryAfter + 1);

                continue;
            }
            throw new RuntimeException("Telegram {$method} failed: ".($body['error_code'] ?? $response->status()).' '.($body['description'] ?? ''));
        }
        throw new RuntimeException("Telegram {$method} failed after 3 attempts");
    }
}
