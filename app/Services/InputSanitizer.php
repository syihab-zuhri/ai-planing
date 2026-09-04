<?php

namespace App\Services;

/**
 * InputSanitizer — normalisasi teks input user sebelum disimpan & sebelum
 * dipakai sebagai prompt AI.
 *
 * Acuan:
 *   - SECURITY.md §6 (Input Validation) — strip control chars, escape quotes/backticks.
 *   - API.md §6 — trim, collapse whitespace, strip control chars.
 *   - PRD/INTAKE.md §7 (BR-INTAKE-002) — textarea di-sanitize sebelum disimpan.
 *
 * Method di sini pure / stateless → aman dipanggil dari mana saja.
 */
class InputSanitizer
{
    /**
     * Hapus karakter kontrol ASCII (U+0000..U+001F kecuali tab/newline/CR)
     * dan karakter DEL (U+007F). Juga menghapus zero-width unicode umum.
     *
     * Whitespace "meaningful" (tab \t=0x09, LF \n=0x0A, CR \r=0x0D) dipertahankan;
     * setelah pass ini, normalizeWhitespace() akan membersihkan sisanya.
     */
    public function stripControlChars(string $input): string
    {
        // 1) Hapus control chars kecuali \t \n \r
        $cleaned = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $input,
        );

        // 2) Hapus zero-width unicode (U+200B..U+200D, U+FEFF)
        $cleaned = preg_replace(
            '/[\x{200B}-\x{200D}\x{FEFF}]/u',
            '',
            $cleaned ?? '',
        );

        return $cleaned ?? '';
    }

    /**
     * Escape karakter berbahaya untuk konteks quote/backtick.
     * Dipakai saat input akan disisipkan ke dalam system/user prompt.
     *
     * Yang di-escape:
     *   - Backtick       ` → \\`
     *   - Double quote   " → \\"
     *   - Backslash      \ → \\ (urut escape harus ini dulu)
     *
     * Output TIDAK di-decode kembali — caller yang menampilkan ke user
     * harus tetap auto-escape via Blade {{ }}.
     */
    public function escapeQuotes(string $input): string
    {
        // Urutan penting: backslash dulu, baru quote marks.
        return str_replace(
            ['\\', '`', '"'],
            ['\\\\', '\\`', '\\"'],
            $input,
        );
    }

    /**
     * Normalisasi whitespace:
     *   - Trim leading/trailing whitespace.
     *   - Ganti CRLF/CR → LF.
     *   - Collapse multiple whitespace (termasuk baris kosong ganda) jadi single.
     *
     * Cocok untuk textarea pendek (project_goal, target_users, dst).
     * Untuk dokumen Markdown panjang, pertimbangkan memanggil stripControlChars
     * saja untuk mempertahankan struktur heading/list.
     */
    public function normalizeWhitespace(string $input): string
    {
        // Normalisasi line ending.
        $normalized = str_replace(["\r\n", "\r"], "\n", $input);

        // Trim trailing whitespace di tiap baris, lalu collapse spasi multiple.
        $lines = explode("\n", $normalized);
        foreach ($lines as &$line) {
            $line = preg_replace('/\s+/u', ' ', $line) ?? '';
        }
        unset($line);

        // Gabungkan kembali dan trim global. Baris kosong antar-baris tetap dipertahankan
        // satu kosong saja (untuk Markdown yang readable).
        $joined = implode("\n", $lines);
        $joined = preg_replace("/\n{3,}/u", "\n\n", $joined) ?? $joined;

        return trim($joined);
    }

    /**
     * Sanitizer komposit: stripControlChars → normalizeWhitespace.
     * Dipakai sebagai default oleh WizardService sebelum simpan ke draft_state.
     */
    public function clean(string $input, bool $normalize = true): string
    {
        $out = $this->stripControlChars($input);
        if ($normalize) {
            $out = $this->normalizeWhitespace($out);
        }
        return $out;
    }
}