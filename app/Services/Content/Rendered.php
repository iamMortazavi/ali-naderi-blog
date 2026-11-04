<?php

namespace App\Services\Content;

final readonly class Rendered
{
    /** @param list<array{level:int,id:string,text:string}> $headings */
    public function __construct(
        public string $html,
        public string $plain,
        public array $headings,
    ) {}
}
