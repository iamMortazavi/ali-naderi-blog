<?php

namespace App\Http\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;

/** List pages of the public site: page 1 is the bare address, later pages add ?page=N. */
class SitePaginator extends LengthAwarePaginator
{
    public function url($page): string
    {
        return $page <= 1 ? $this->path() : parent::url($page);
    }
}
