<?php

namespace Tests\Mocks;

/**
 * Reference implementation of the input sanitizer contract described
 * in SECURITY.md §6 and PRD/INTAKE §8.
 *
 * The production backend will replace this stub with a real service
 * (e.g. `App\Services\Security\InputSanitizer`). This stub mirrors the
 * expected behavior so unit tests can exercise the contract today.
 *
 * Contract:
 *   - stripControlChars(string): removes ASCII < 0x20 (except \t \n \r)
 *     and the Unicode zero-width chars used by some prompt-injection tricks.
 *   - escapeQuotes(string): escapes ", ', `, and \ so values can be
 *     embedded in a Markdown/code-block context safely.
 *   - normalizeWhitespace(string): trims, collapses runs of whitespace
 *     to a single space, and normalizes CRLF / NBSP variants.
 */
class InputSanitizerStub
{
    /**
     * Zero-width and other invisible characters we never want in prompts.
     */
    private const INVISIBLE_CHARS = [
        "\u{200B}", // zero-width space
        "\u{200C}", // zero-width non-joiner
        "\u{200D}", // zero-width joiner
        "\u{FEFF}", // byte-order mark / zero-width no-break space
        "\u{2060}", // word joiner
        "\u{180E}", // Mongolian vowel separator
    ];

    public function stripControlChars(string $input): string
    {
        // Strip ASCII control chars except tab, LF, CR.
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);

        // Strip common zero-width unicode characters used in prompt injection.
        $cleaned = str_replace(self::INVISIBLE_CHARS, '', $cleaned);

        return $cleaned;
    }

    public function escapeQuotes(string $input): string
    {
        // Order matters: escape backslashes first, then the quote chars.
        return addcslashes($input, "\\\"'`");
    }

    public function normalizeWhitespace(string $input): string
    {
        // Normalize line endings and replace non-breaking spaces with regular ones.
        $normalized = str_replace(["\r\n", "\r", "\u{00A0}", "\u{2007}", "\u{202F}"], ["\n", "\n", ' ', ' ', ' '], $input);

        // Trim and collapse runs of whitespace into single space.
        $normalized = trim($normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);

        return $normalized;
    }
}