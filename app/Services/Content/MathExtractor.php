<?php

namespace App\Services\Content;

/**
 * Keeps $inline$ and $$block$$ TeX away from the Markdown parser: extract() swaps each formula
 * for a placeholder, restore() puts it back as an element that the browser renders (Temml).
 * One instance per document.
 */
final class MathExtractor
{
    /** @var list<array{0:'block'|'inline',1:string}> */
    private array $formulas = [];

    /** Replace the math in a piece of prose (never code) with placeholders. */
    public function extract(string $text): string
    {
        $text = preg_replace_callback('/\$\$(.+?)\$\$/s', fn ($m) => "\n\n".$this->token('block', trim($m[1]))."\n\n", $text);

        return preg_replace_callback(
            '/(?<![\\\\$\w])\$(?!\s)([^$\n]+?)(?<!\s)\$(?![\w$])/u',
            fn ($m) => $this->token('inline', $m[1]),
            $text,
        );
    }

    /** Turn the placeholders in rendered HTML into math elements; a block formula leaves its paragraph. */
    public function restore(string $html): string
    {
        if ($this->formulas === []) {
            return $html;
        }

        return preg_replace_callback('/(<p>)?MATHTOKEN(\d+)END(<\/p>)?/', function ($m) {
            [$type, $tex] = $this->formulas[(int) $m[2]] ?? ['inline', ''];
            $tex = htmlspecialchars($tex, ENT_QUOTES);
            if ($type === 'block') {
                return '<div class="math math-block" dir="ltr">'.$tex.'</div>';
            }

            return $m[1].'<span class="math math-inline" dir="ltr">'.$tex.'</span>'.($m[3] ?? '');
        }, $html);
    }

    private function token(string $type, string $tex): string
    {
        $this->formulas[] = [$type, $tex];

        return 'MATHTOKEN'.(count($this->formulas) - 1).'END';
    }
}
