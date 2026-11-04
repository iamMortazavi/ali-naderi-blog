<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * One Obsidian import. Kept for 90 days (`php artisan model:prune`, scheduled daily).
 *
 * @property int $id
 * @property string $source
 * @property SyncStatus $status
 * @property int $created_count
 * @property int $updated_count
 * @property int $unpublished_count
 * @property int $unchanged_count
 * @property int $skipped_count
 * @property list<array{level:string,file:string|null,text:string}>|null $messages
 * @property string|null $commit
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'source', 'status', 'created_count', 'updated_count', 'unpublished_count', 'unchanged_count', 'skipped_count',
    'messages', 'commit', 'started_at', 'finished_at',
])]
class SyncRun extends Model
{
    use Prunable;

    protected function casts(): array
    {
        return ['status' => SyncStatus::class, 'messages' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(90));
    }

    public function errorCount(): int
    {
        return collect($this->messages ?? [])->where('level', 'error')->count();
    }
}
