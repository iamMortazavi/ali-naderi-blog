<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source', 'status', 'created_count', 'updated_count', 'unpublished_count', 'unchanged_count', 'skipped_count',
    'messages', 'commit', 'started_at', 'finished_at',
])]
class SyncRun extends Model
{
    protected function casts(): array
    {
        return ['messages' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function errorCount(): int
    {
        return collect($this->messages)->where('level', 'error')->count();
    }
}
