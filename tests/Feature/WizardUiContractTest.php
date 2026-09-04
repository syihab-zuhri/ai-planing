<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WizardUiContractTest extends TestCase
{
    #[DataProvider('wizardSteps')]
    public function test_wizard_forms_submit_to_api_and_redirect(string $path, string $endpoint, string $next): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee('action="'.url($endpoint).'"', false)
            ->assertSee("@submit.prevent=\"submitAndContinue('{$next}')\"", false);
    }

    public static function wizardSteps(): array
    {
        return [
            'intake' => ['/wizard/step/intake', '/api/wizard/intake', '/wizard/step/domain'],
            'domain' => ['/wizard/step/domain', '/api/wizard/domain', '/wizard/step/scope'],
            'scope' => ['/wizard/step/scope', '/api/wizard/scope', '/wizard/step/architecture'],
            'architecture' => ['/wizard/step/architecture', '/api/wizard/architecture', '/wizard/step/clarify'],
        ];
    }

    public function test_select_values_match_backend_validation_contract(): void
    {
        $this->get('/wizard/step/domain')
            ->assertSee('value="Web"', false)
            ->assertSee('value="&lt;100"', false)
            ->assertSee('value="100-1k"', false);

        $this->get('/wizard/step/architecture')
            ->assertSee('value="Laravel+Blade"', false)
            ->assertSee('value="WSL"', false)
            ->assertSee('value="Internal"', false);
    }
}
