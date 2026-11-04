<?php

namespace App\Filament\Resources\Posts\Actions;

use App\Enums\TelegramStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\Telegram\TelegramPublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * "Send to Telegram" and "retry Telegram" for the posts table and the post pages.
 * Errors are reported to the log only: an exception text may contain the bot token.
 */
class TelegramActions
{
    public static function send(): Action
    {
        return Action::make('telegram')
            ->label('ارسال به تلگرام')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalDescription('این نوشته همین حالا در کانال منتشر می‌شود. هر نوشته فقط یک بار ارسال می‌شود.')
            ->visible(fn (Post $record) => $record->isLive()
                && ! $record->trashed()
                && ! $record->telegramMessage
                && app(TelegramPublisher::class)->configured())
            ->authorize(fn (Post $record) => self::canSend($record))
            ->action(function (Post $record) {
                try {
                    app(TelegramPublisher::class)->send($record);
                    Notification::make()->title('در کانال تلگرام منتشر شد')->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    self::failed();
                }
            });
    }

    public static function retry(): Action
    {
        return Action::make('telegramRetry')
            ->label('ارسال دوباره به تلگرام')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn (Post $record) => $record->telegramMessage?->status === TelegramStatus::Sending
                ? 'نتیجه‌ی ارسال قبلی معلوم نیست. اول کانال را نگاه کنید: اگر پیام آن‌جا هست، دوباره نفرستید.'
                : 'ارسال قبلی ناموفق بود. این نوشته دوباره به کانال فرستاده می‌شود.')
            ->visible(fn (Post $record) => in_array($record->telegramMessage?->status, [TelegramStatus::Failed, TelegramStatus::Sending], true)
                && ! $record->trashed()
                && app(TelegramPublisher::class)->configured())
            ->authorize(fn () => self::user()?->isAdmin() ?? false)
            ->action(function (Post $record) {
                try {
                    app(TelegramPublisher::class)->retry($record);
                    Notification::make()->title('در کانال تلگرام منتشر شد')->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    self::failed();
                }
            });
    }

    /** Last error of the post's channel message, with the bot token removed just in case. */
    public static function lastError(?Post $post): ?string
    {
        $error = $post?->telegramMessage?->last_error;
        if (blank($error)) {
            return null;
        }
        $token = (string) config('services.telegram.bot_token');

        return $token !== '' ? str_replace($token, '***', $error) : $error;
    }

    private static function canSend(Post $post): bool
    {
        $user = self::user();

        return $user !== null && ($user->isAdmin() || (int) $post->author_id === (int) $user->getKey());
    }

    private static function failed(): void
    {
        Notification::make()
            ->title('ارسال به تلگرام انجام نشد')
            ->body('جزئیات خطا در گزارش سرور ثبت شد. چند دقیقه‌ی دیگر دوباره امتحان کنید.')
            ->danger()
            ->send();
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
