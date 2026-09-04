<?php

namespace Tests\Mocks;

/**
 * Reference implementation of the prompt-injection detector contract
 * described in SECURITY.md §3 (TM-001).
 *
 * The production backend will replace this stub with a real detector
 * (e.g. `App\Services\Security\PromptInjectionDetector`). This stub
 * mirrors the expected behavior so unit tests can exercise the
 * contract today.
 *
 * Contract:
 *   - detect(string $input): returns true if any known injection pattern
 *     is found in the input. Case-insensitive. Substring match.
 */
class PromptInjectionDetectorStub
{
    /**
     * Canonical patterns from SECURITY.md §3 (TM-001) plus common
     * injection phrasings observed in production LLM red-teaming.
     *
     * Patterns are matched as plain substrings (case-insensitive).
     */
    private const PATTERNS = [
        'ignore previous instructions',
        'ignore all previous',
        'disregard previous',
        'disregard all',
        'forget previous',
        'forget all',
        'system:',
        'assistant:',
        '### instruction',
        '### system',
        'you are now',
        'act as',
        'pretend to be',
        'new instructions:',
        'override system',
        'jailbreak',
        'developer mode',
        'do anything now',
    ];

    /**
     * True if the input matches any known injection pattern.
     */
    public function detect(string $input): bool
    {
        $needle = mb_strtolower($input);

        foreach (self::PATTERNS as $pattern) {
            if (str_contains($needle, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the list of patterns that matched, useful for reporting.
     *
     * @return array<int, string>
     */
    public function matchedPatterns(string $input): array
    {
        $needle = mb_strtolower($input);
        $hits = [];

        foreach (self::PATTERNS as $pattern) {
            if (str_contains($needle, $pattern)) {
                $hits[] = $pattern;
            }
        }

        return $hits;
    }
}