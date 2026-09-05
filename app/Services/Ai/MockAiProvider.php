<?php

namespace App\Services\Ai;

/**
 * MockAiProvider — implementasi default yang TIDAK memanggil jaringan luar.
 *
 * Dipakai untuk:
 *   - Development lokal (OQ-001 belum resolved → API key 9router belum tersedia).
 *   - Unit/Feature test yang tidak ingin hit provider asli.
 *
 * Untuk menukar ke provider sungguhan, bind AiProviderInterface di
 * App\Providers\AppServiceProvider dengan class konkret lain.
 */
class MockAiProvider implements AiProviderInterface
{
    /**
     * Aturan estimator token sederhana (4 karakter ≈ 1 token), cukup untuk
     * menampilkan estimasi ke user (SRS NFR-011, PRD/GENERATION §2).
     */
    private const CHARS_PER_TOKEN = 4;

    /**
     * Tunda simulasi latency (ms) untuk membuat perilaku mirip provider asli.
     * Default 0 untuk test cepat.
     */
    public int $simulatedLatencyMs = 0;

    /**
     * Mock response yang akan dikembalikan oleh chat().
     * Boleh di-set dari test untuk verifikasi prompt assembly.
     *
     * Panjang default sengaja > 200 karakter dan memiliki heading level 1 agar
     * lolos MarkdownValidator (BR-GEN-004) — sehingga pipeline tetap bisa
     * dijalankan end-to-end tanpa provider sungguhan.
     */
    public string $mockContent = <<<'MARKDOWN'
# Mock Document

Dokumen ini dihasilkan oleh MockAiProvider untuk keperluan pengembangan dan
pengujian. Isinya sengaja bersifat umum dan tidak mencerminkan konteks proyek
yang sesungguhnya.

## Ringkasan

Provider mock dipakai ketika konfigurasi provider sungguhan (9router atau
endpoint OpenAI-compatible) belum lengkap. Pipeline generate, validasi gate,
dan ekspor ZIP tetap dapat dijalankan penuh dengan keluaran ini.

## Catatan

Ganti `AI_PROVIDER_PRIMARY` ke provider sungguhan untuk memperoleh dokumen yang
benar-benar disusun sesuai data wizard.
MARKDOWN;

    public function chat(array $messages, array $options = []): AiResponse
    {
        if ($this->simulatedLatencyMs > 0) {
            usleep($this->simulatedLatencyMs * 1000);
        }

        $promptChars = $this->measureMessages($messages);

        return new AiResponse(
            content: $this->mockContent,
            tokens_in: $this->charsToTokens($promptChars),
            tokens_out: $this->charsToTokens(mb_strlen($this->mockContent)),
            latency_ms: $this->simulatedLatencyMs,
            provider: $this->name(),
        );
    }

    public function estimateTokens(string|array $prompt): int
    {
        if (is_string($prompt)) {
            return $this->charsToTokens(mb_strlen($prompt));
        }

        return $this->charsToTokens($this->measureMessages($prompt));
    }

    public function name(): string
    {
        return 'mock';
    }

    private function measureMessages(array $messages): int
    {
        $total = 0;
        foreach ($messages as $msg) {
            $total += mb_strlen((string) ($msg['content'] ?? ''));
        }
        return $total;
    }

    private function charsToTokens(int $chars): int
    {
        return (int) max(1, ceil($chars / self::CHARS_PER_TOKEN));
    }
}