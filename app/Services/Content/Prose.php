<?php

namespace App\Services\Content;

/**
 * Applies a transformation to the prose parts of Markdown only: fenced code blocks and
 * inline code spans pass through untouched.
 */
final class Prose
{
    /** @return list<array{0:bool,1:string}> [isCode, chunk] */
    public static function splitFenced(string $text): array
    {
        $chunks = [];
        $buffer = '';
        $fence = null;
        foreach (preg_split('/(?<=\n)/', $text) as $line) {
            if ($line === '') {
                continue;
            }
            $isFence = preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m);
            if ($fence === null && $isFence) {
                if ($buffer !== '') {
                    $chunks[] = [false, $buffer];
                }
                $buffer = $line;
                $fence = $m[1];
            } elseif ($fence !== null && $isFence && $m[1][0] === $fence[0]
                && strlen($m[1]) >= strlen($fence) && trim($line) === $m[1]) {
                $chunks[] = [true, $buffer.$line];
                $buffer = '';
                $fence = null;
            } else {
                $buffer .= $line;
            }
        }
        if ($buffer !== '') {
            $chunks[] = [$fence !== null, $buffer];
        }

        return $chunks;
    }

    /**
     * Apply $fn to the prose between code; Obsidian %% private comments %% are removed first.
     *
     * @param  callable(string):string  $fn
     */
    public static function map(string $markdown, callable $fn): string
    {
        $out = '';
        foreach (self::splitFenced($markdown) as [$isCode, $chunk]) {
            if ($isCode) {
                $out .= $chunk;

                continue;
            }
            $chunk = preg_replace('/%%.*?%%/s', '', $chunk);
            $parts = preg_split('/(`+)(.+?)\1/s', $chunk, -1, PREG_SPLIT_OFFSET_CAPTURE);
            preg_match_all('/(`+)(.+?)\1/s', $chunk, $codes);
            foreach ($parts as $i => [$text]) {
                $out .= $fn($text);
                if (isset($codes[0][$i])) {
                    $out .= $codes[0][$i];
                }
            }
        }

        return $out;
    }
}
