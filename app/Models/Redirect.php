<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['from_path', 'to_path', 'status_code'])]
class Redirect extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect) {
            $redirect->from_path = '/'.trim(parse_url($redirect->from_path, PHP_URL_PATH) ?? '', '/');
        });
    }
}
