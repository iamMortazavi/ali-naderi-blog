<?php

namespace App\Models;

use App\Enums\TelegramStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single channel message of a post. See App\Services\Telegram\TelegramPublisher.
 *
 * @property int $id
 * @property int $post_id
 * @property string $chat_id
 * @property TelegramStatus $status
 * @property int|null $message_id
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 */
#[Fillable(['post_id', 'chat_id', 'status', 'message_id', 'attempts', 'last_error', 'sent_at'])]
class TelegramMessage extends Model
{
    protected function casts(): array
    {
        return ['status' => TelegramStatus::class, 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
