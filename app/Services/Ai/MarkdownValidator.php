<?php

namespace App\Services\Ai;

/**
 * MarkdownValidator — validasi minimum output AI (BR-GEN-004, PRD/GENERATION.md §8).
 *
 * Aturan (sengaja longgar: tujuannya menangkap output rusak, bukan menilai mutu):
 *   1. Body tidak kosong setelah trim.
 *   2. Ada minimal satu heading level 1 (`# `).
 *   3. Panjang minimum (default 200 karakter, selaras ValidateController).
 *   4. Tidak berisi tag <script> (PRD/GENERATION.md §15 — sanitasi output).
 *   5. Bukan sisa pembungkus code fence penuh (```markdown ... ```).
 */
class MarkdownValidator
{
    public function __construct(
        private readonly int $minChars = 200,
    ) {
    }

    /**
     * @return array{valid:bool,reason:?string}
     */
    public function validate(string $content): array
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return ['valid' => false, 'reason' => 'Output kosong.'];
        }

        if (mb_strlen($trimmed) < $this->minChars) {
            return [
                'valid'  => false,
                'reason' => 'Output terlalu pendek (' . mb_strlen($trimmed) . ' < ' . $this->minChars . ' karakter).',
            ];
        }

        if (!preg_match('/^#\s+\S/m', $trimmed)) {
            return ['valid' => false, 'reason' => 'Output tidak memiliki heading level 1.'];
        }

        if (preg_match('/<\s*script/i', $trimmed)) {
            return ['valid' => false, 'reason' => 'Output mengandung tag script.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function isValid(string $content): bool
    {
        return $this->validate($content)['valid'];
    }

    /**
     * Bersihkan artefak umum LLM sebelum disimpan:
     *   - Pembungkus code fence menyeluruh (```markdown ... ```).
     *   - Control character selain tab/newline.
     *   - Tag <script>...</script>.
     */
    public function sanitize(string $content): string
    {
        $clean = trim($content);

        // Buang fence pembungkus jika SELURUH dokumen terbungkus.
        if (preg_match('/^```[a-zA-Z]*\s*\n(.*)\n```$/s', $clean, $matches) === 1) {
            $clean = trim($matches[1]);
        }

        $clean = preg_replace('#<\s*script.*?>.*?<\s*/\s*script\s*>#is', '', $clean) ?? $clean;
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? $clean;

        return trim($clean);
    }
}
