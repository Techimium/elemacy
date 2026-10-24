<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility;

defined('ABSPATH') || exit;

/**
 * Reads the output of a theme's header.php or footer.php that the generic
 * adapter discarded, and returns only the layout wrappers it leaves
 * unbalanced: the opening tags it never closes and the closing tags it closes
 * without having opened them. A classic theme opens #page, #content and main
 * in header.php and closes them in footer.php, so replacing one side without
 * these would leave the page unbalanced. See docs/ARCHITECTURE.md § Native
 * chrome replacement.
 *
 * Only wrapper tags are returned, as written, attributes included. Text,
 * navigation, scripts, comments and the chrome element are never returned. Any
 * markup this cannot read with certainty makes the whole result "unreadable"
 * (null), and the caller then re-emits nothing for that side.
 */
final class LayoutWrapperReader
{
    /** Structural containers whose ids and classes carry the theme's layout CSS. */
    private const WRAPPERS = ['div', 'main', 'section', 'article', 'aside'];

    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /** Elements HTML allows to end without a closing tag. */
    private const OPTIONAL_END = ['p', 'li', 'dt', 'dd', 'option', 'optgroup', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot', 'colgroup', 'caption', 'rt', 'rp'];

    /** The document shell belongs to Elemacy's views, not to the theme file. */
    private const SHELL = ['html', 'head', 'body'];

    /**
     * Comments, doctype, processing instructions and raw-text elements are
     * skipped whole; everything else matching is a tag.
     */
    private const TOKEN = '~<!--.*?-->|<![^>]*>|<\?.*?\?>|<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>|<(/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:"[^"]*"|\'[^\']*\'|[^\'">])*)>~is';

    /**
     * @param string $html The discarded output.
     * @param string $side 'header' (chrome element: the first <header>) or 'footer' (the last <footer>).
     * @return array{before: string, after: string}|null The tags to print before and after the Elemacy
     *         template, or null when the output cannot be read with certainty.
     */
    public function read(string $html, string $side): ?array
    {
        $tags = $this->tags($html);
        if (null === $tags) {
            return null;
        }

        $chrome_tag = 'header' === $side ? 'header' : 'footer';
        $chrome_at = null;
        $open = [];
        $unbalanced = [];

        foreach ($tags as [$at, $raw, $closing, $name]) {
            if (in_array($name, self::SHELL, true)) {
                continue;
            }
            if (!$closing && $name === $chrome_tag && ('footer' === $side || null === $chrome_at)) {
                $chrome_at = $at;
            }
            if (in_array($name, self::VOID, true) || '/>' === substr($raw, -2)) {
                continue;
            }
            if (!$closing) {
                $open[] = [$name, $at, $raw];
                continue;
            }

            $index = $this->last_open($open, $name);
            if (null === $index) {
                if (!in_array($name, self::WRAPPERS, true)) {
                    return null;
                }
                $unbalanced[] = [$at, $raw];
                continue;
            }
            // Everything opened after the match is implicitly closed by this tag.
            $skipped = array_splice($open, $index);
            foreach (array_slice($skipped, 1) as $implied) {
                if (!in_array($implied[0], self::OPTIONAL_END, true)) {
                    return null;
                }
            }
        }

        foreach ($open as [$name, $at, $raw]) {
            if (in_array($name, self::OPTIONAL_END, true)) {
                continue;
            }
            if (!in_array($name, self::WRAPPERS, true)) {
                return null;
            }
            $unbalanced[] = [$at, $raw];
        }

        usort($unbalanced, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $result = [
            'before' => '',
            'after' => '',
        ];
        foreach ($unbalanced as [$at, $raw]) {
            $result[null === $chrome_at || $at < $chrome_at ? 'before' : 'after'] .= $raw;
        }

        return $result;
    }

    /**
     * @return array<int, array{int, string, bool, string}>|null [offset, raw tag, is closing, lowercase name];
     *         null when text between tags still contains markup that is not a tag (a tag cut off, an unterminated script).
     */
    private function tags(string $html): ?array
    {
        if (false === preg_match_all(self::TOKEN, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $tags = [];
        $cursor = 0;
        foreach ($matches as $match) {
            [$raw, $at] = $match[0];
            if ($this->has_stray_markup(substr($html, $cursor, $at - $cursor))) {
                return null;
            }
            $cursor = $at + strlen($raw);
            if (!isset($match[2]) || '' === $match[2][0]) {
                continue;
            }
            $tags[] = [$at, $raw, '/' === $match[1][0], strtolower($match[2][0])];
        }

        return $this->has_stray_markup(substr($html, $cursor)) ? null : $tags;
    }

    private function has_stray_markup(string $text): bool
    {
        return 1 === preg_match('~<[a-zA-Z/!?]~', $text);
    }

    /** @param array<int, array{string, int, string}> $open */
    private function last_open(array $open, string $name): ?int
    {
        for ($i = count($open) - 1; $i >= 0; $i--) {
            if ($open[$i][0] === $name) {
                return $i;
            }
        }

        return null;
    }
}
