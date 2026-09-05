<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseTwoUiTest extends TestCase
{
    /**
     * Halaman /generate memakai komponen Alpine `generateProgress`, yang
     * memegang endpoint start/status/retry di resources/js/app.js (bukan lagi
     * inline di Blade). Uji keduanya supaya wiring tetap terjaga.
     */
    public function test_generate_page_is_wired_to_generate_api(): void
    {
        $this->get('/generate')
            ->assertOk()
            ->assertSee('generatePage', false)
            ->assertSee('generateProgress()', false);

        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("'/api/generate/start'", $js);
        $this->assertStringContainsString("'/api/generate/status'", $js);
        $this->assertStringContainsString('/api/generate/retry/', $js);
    }

    public function test_validate_page_is_wired_to_validation_api(): void
    {
        $this->get('/validate')
            ->assertOk()
            ->assertSee('validatePage', false)
            ->assertSee('/api/validate/run', false);
    }

    public function test_export_page_is_wired_to_export_api(): void
    {
        $this->get('/export')
            ->assertOk()
            ->assertSee('exportPage', false)
            ->assertSee('/api/export/start', false);
    }

    public function test_clarify_continues_to_generate_page(): void
    {
        $this->get('/wizard/step/clarify')
            ->assertOk()
            ->assertSee("submitAndContinue('/generate')", false);
    }
}
