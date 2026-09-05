<?php

namespace Tests\Unit;

use App\Services\Ai\MarkdownValidator;
use PHPUnit\Framework\TestCase;

/**
 * MarkdownValidatorTest — BR-GEN-004 (validasi output AI) + PRD/GENERATION §15.
 */
class MarkdownValidatorTest extends TestCase
{
    private function longBody(string $heading = '# Judul Dokumen'): string
    {
        return $heading . "\n\n" . str_repeat('Isi dokumen yang cukup panjang. ', 12);
    }

    public function test_valid_document_passes(): void
    {
        $validator = new MarkdownValidator();
        $result = $validator->validate($this->longBody());

        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
        $this->assertTrue($validator->isValid($this->longBody()));
    }

    public function test_empty_content_fails(): void
    {
        $result = (new MarkdownValidator())->validate("   \n  ");

        $this->assertFalse($result['valid']);
        $this->assertSame('Output kosong.', $result['reason']);
    }

    public function test_short_content_fails(): void
    {
        $result = (new MarkdownValidator())->validate("# Judul\n\nTerlalu pendek.");

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('terlalu pendek', $result['reason']);
    }

    public function test_missing_h1_fails(): void
    {
        $result = (new MarkdownValidator())->validate('## Bukan H1 ' . str_repeat('x', 250));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('heading level 1', $result['reason']);
    }

    public function test_h1_can_appear_after_first_line(): void
    {
        $content = "Catatan pembuka singkat.\n\n# Judul Sebenarnya\n\n" . str_repeat('teks. ', 60);

        $this->assertTrue((new MarkdownValidator())->isValid($content));
    }

    public function test_script_tag_fails(): void
    {
        $content = $this->longBody() . "\n\n<script>alert(1)</script>";
        $result = (new MarkdownValidator())->validate($content);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('script', $result['reason']);
    }

    public function test_custom_min_chars_is_respected(): void
    {
        $validator = new MarkdownValidator(20);

        $this->assertTrue($validator->isValid("# Judul\n\nCukup untuk ambang 20."));
    }

    public function test_sanitize_removes_wrapping_code_fence(): void
    {
        $inner = $this->longBody();
        $wrapped = "```markdown\n{$inner}\n```";

        // sanitize() melakukan trim, jadi bandingkan dengan versi ter-trim.
        $this->assertSame(trim($inner), (new MarkdownValidator())->sanitize($wrapped));
    }

    public function test_sanitize_keeps_inner_code_fences(): void
    {
        $content = "# Judul\n\n```php\n\$x = 1;\n```\n\n" . str_repeat('teks. ', 40);
        $sanitized = (new MarkdownValidator())->sanitize($content);

        $this->assertStringContainsString('```php', $sanitized);
        $this->assertTrue((new MarkdownValidator())->isValid($sanitized));
    }

    public function test_sanitize_strips_script_and_control_chars(): void
    {
        $content = "# Judul\x07\n\n<script>bad()</script>\n\n" . str_repeat('teks. ', 40);
        $sanitized = (new MarkdownValidator())->sanitize($content);

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString("\x07", $sanitized);
    }

    public function test_sanitize_preserves_newlines_and_tabs(): void
    {
        $content = "# Judul\n\n\tindentasi\n\n" . str_repeat('teks. ', 40);
        $sanitized = (new MarkdownValidator())->sanitize($content);

        $this->assertStringContainsString("\n", $sanitized);
        $this->assertStringContainsString("\t", $sanitized);
    }
}
