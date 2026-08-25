<?php

namespace App\Services\Chat;

class ChatResponseFormatter
{
    /**
     * Remove common decorative Markdown from final assistant display text.
     * Tool payloads, structured actions, and user messages never pass here.
     */
    public function format(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $urls = [];
        $text = preg_replace_callback(
            '~https?://[^\s<>]+~u',
            function (array $match) use (&$urls): string {
                $token = "\x1AURL".count($urls)."\x1A";
                $urls[$token] = $match[0];
                return $token;
            },
            $text
        ) ?? $text;

        // Markdown headings at the beginning of a line. This cannot affect
        // URL fragments because content before a # is required for a URL.
        $text = preg_replace('/^\h{0,3}#{1,6}\h+/mu', '', $text) ?? $text;

        // Remove paired emphasis markers without changing their contents.
        $text = preg_replace('/\*{3}([^*\n]+)\*{3}/u', '$1', $text) ?? $text;
        $text = preg_replace('/\*{2}([^*\n]+)\*{2}/u', '$1', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '$1', $text) ?? $text;
        $text = preg_replace('/__([^_\n]+)__/u', '$1', $text) ?? $text;

        // Strip only list markers followed by whitespace at line start.
        // Hyphenated names, negative values, dates, ranges, and URLs remain.
        $text = preg_replace('/^\h{0,3}[-*]\h+/mu', '', $text) ?? $text;

        // Remove trailing horizontal whitespace and allow at most one blank
        // line between logical sections.
        $text = preg_replace('/\h+$/mu', '', $text) ?? $text;
        $text = preg_replace('/\n\h*\n(?:\h*\n)+/u', "\n\n", $text) ?? $text;

        return strtr(trim($text), $urls);
    }
}
