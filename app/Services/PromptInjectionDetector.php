<?php

namespace App\Services;

/**
 * PromptInjectionDetector — mendeteksi pola upaya prompt injection pada
 * input user (SECURITY.md TM-001).
 *
 * Strategi: signature-based + heuristic. TIDAK menggantikan validasi
 * downstream (sanitizer + system prompt anchor), tetapi memberikan flag
 * untuk di-review sebelum dipakai sebagai prompt AI.
 *
 * Usage:
 *   $detector = new PromptInjectionDetector();
 *   $flags = $detector->detect($userInput);
 *   // => ['detected' => bool, 'reasons' => string[], 'risk' => 'low'|'medium'|'high']
 */
class PromptInjectionDetector
{
    /**
     * Daftar pola signature. Lower-case sudah dinormalisasi saat pencocokan.
     *
     * @var array<string, array{pattern: string, severity: string, reason: string}>
     */
    private const SIGNATURES = [
        'ignore_previous' => [
            'pattern'  => '/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions?|prompts?|rules?)/i',
            'severity' => 'high',
            'reason'   => 'upaya override instruksi sistem ("ignore previous instructions")',
        ],
        'disregard_system' => [
            'pattern'  => '/(disregard|forget|override)\s+(the\s+)?(system|previous|all)\s+(prompt|rule|instruction)/i',
            'severity' => 'high',
            'reason'   => 'upaya override aturan sistem',
        ],
        'system_role_injection' => [
            'pattern'  => '/^\s*system\s*:/im',
            'severity' => 'high',
            'reason'   => 'penggunaan "system:" untuk menyisipkan pesan sistem',
        ],
        'role_tag_injection' => [
            'pattern'  => '/<\/?\s*(system|assistant|user)\s*>/i',
            'severity' => 'high',
            'reason'   => 'tag role chat-format untuk manipulasi pesan',
        ],
        'markdown_instruction' => [
            'pattern'  => '/^#{1,6}\s*instruction/im',
            'severity' => 'medium',
            'reason'   => 'heading Markdown yang meniru "### instruction"',
        ],
        'output_format_hijack' => [
            'pattern'  => '/(respond|answer|reply)\s+(only\s+)?(in|with|using)\s+(json|yaml|code|english|indonesia)/i',
            'severity' => 'medium',
            'reason'   => 'upaya memaksa format output tertentu',
        ],
        'jailbreak_keyword' => [
            'pattern'  => '/\b(jailbreak|DAN|do anything now)\b/i',
            'severity' => 'high',
            'reason'   => 'keyword jailbreak populer',
        ],
        'hidden_command' => [
            'pattern'  => '/(\[INST\]|\[\/INST\]|<<SYS>>|<\/SYS>>|<\|im_start\|>|<\|im_end\|>)/i',
            'severity' => 'high',
            'reason'   => 'token kontrol chat-format dari model lain (LLaMA, Mistral, ChatML)',
        ],
    ];

    /**
     * Deteksi pola injection pada input.
     *
     * @return array{
     *   detected: bool,
     *   risk: 'none'|'low'|'medium'|'high',
     *   reasons: string[],
     *   signatures: string[]
     * }
     */
    public function detect(string $input): array
    {
        $reasons = [];
        $signaturesHit = [];
        $maxSeverity = 'none';

        foreach (self::SIGNATURES as $key => $sig) {
            if (preg_match($sig['pattern'], $input) === 1) {
                $reasons[] = $sig['reason'];
                $signaturesHit[] = $key;
                if ($this->severityRank($sig['severity']) > $this->severityRank($maxSeverity)) {
                    $maxSeverity = $sig['severity'];
                }
            }
        }

        return [
            'detected'   => !empty($reasons),
            'risk'       => $maxSeverity,
            'reasons'    => $reasons,
            'signatures' => $signaturesHit,
        ];
    }

    /**
     * Helper: true jika terdeteksi risk medium ke atas.
     */
    public function isRisky(string $input): bool
    {
        $result = $this->detect($input);
        return in_array($result['risk'], ['medium', 'high'], true);
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'high'   => 3,
            'medium' => 2,
            'low'    => 1,
            default  => 0,
        };
    }
}