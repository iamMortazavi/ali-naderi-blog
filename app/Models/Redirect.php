<?php

namespace App\Models;

use App\Support\Url;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property int $hits
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['from_path', 'to_path', 'status_code'])]
class Redirect extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect) {
            $redirect->from_path = Url::path($redirect->from_path);
        });
    }
}
