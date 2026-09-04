<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseTwoUiTest extends TestCase
{
    public function test_generate_page_is_wired_to_generate_api(): void
    {
        $this->get('/generate')
            ->assertOk()
            ->assertSee('generatePage', false)
            ->assertSee('/api/generate/start', false);
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
