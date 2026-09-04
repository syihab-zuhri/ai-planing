<?php

namespace Tests\Feature;

use App\Models\Project;
use Tests\TestCase;

/**
 * ValidateApiTest — endpoint /api/validate/* (Gate A/B/C/D logic).
 *
 * Acuan: PRD/VALIDATION.md + API.md §3.
 */
class ValidateApiTest extends TestCase
{

    /**
     * GET /api/validate/status tanpa project → gate null + message.
     */
    public function test_status_without_project_returns_null(): void
    {
        $response = $this->getJson('/api/validate/status');

        $response->assertOk();
        $response->assertJsonPath('gate', null);
    }

    /**
     * POST /api/validate/run dengan intake kosong → banyak blockers.
     */
    public function test_run_with_empty_state_yields_gate_a_with_blockers(): void
    {
        // Hanya start — tanpa wizard.
        $this->postJson('/api/wizard/start')->assertOk();

        $response = $this->postJson('/api/validate/run')->assertOk();

        $response->assertJsonPath('gate', 'A');
        $this->assertNotEmpty($response->json('blockers'));
    }

    /**
     * POST /api/validate/run setelah wizard lengkap (no generate) → Gate B.
     */
    public function test_run_with_full_wizard_yields_gate_b(): void
    {
        $this->seedFullWizard();

        $response = $this->postJson('/api/validate/run')->assertOk();

        $response->assertJsonPath('gate', 'B');
        $this->assertSame([], $response->json('blockers'));
    }

    /**
     * Override Gate B ditolak (BR-VALID-004) → validation error.
     */
    public function test_override_gate_b_is_rejected(): void
    {
        $this->seedFullWizard();

        $response = $this->postJson('/api/validate/override', [
            'gate'   => 'B',
            'reason' => 'Just want to bypass the gate requirement quickly.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gate']);
    }

    /**
     * Override Gate C dengan reason valid → 200 + override_id.
     */
    public function test_override_gate_c_succeeds_with_valid_reason(): void
    {
        $this->seedFullWizard();

        $response = $this->postJson('/api/validate/override', [
            'gate'   => 'C',
            'reason' => 'Saya sudah review manual dan yakin dokumen siap untuk export.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('gate', 'C');
        $response->assertJsonPath('overridden', true);
        $this->assertNotEmpty($response->json('override_id'));
    }

    /**
     * Override dengan reason < 20 char → validation error (PRD/VALIDATION §8 BR-VALID-002).
     */
    public function test_override_requires_min_20_chars_reason(): void
    {
        $this->seedFullWizard();

        $response = $this->postJson('/api/validate/override', [
            'gate'   => 'C',
            'reason' => 'too short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    /* -------------------------------------------------------------- */

    private function seedFullWizard(): void
    {
        $this->postJson('/api/wizard/intake', [
            'project_name' => 'Gamma',
            'project_goal' => 'A web platform for managing freelance contracts end to end.',
            'target_users' => 'Solo freelancers in Indonesia needing contracts.',
            'known_constraints' => 'Limited budget.',
        ])->assertOk();

        $this->postJson('/api/wizard/domain', [
            'domain_category'     => 'Web',
            'problem_statement'   => 'Freelancers juggle multiple contracts manually.',
            'value_proposition'   => 'Automate contract lifecycle.',
            'scale_estimate_mvp'  => '<100',
            'scale_estimate_12mo' => '1k-10k',
        ])->assertOk();

        $this->postJson('/api/wizard/scope', [
            'p0_features'  => ['Auth', 'Contract CRUD'],
            'p1_features'  => ['Notifications'],
            'p2_features'  => [],
            'out_of_scope' => ['Payment'],
        ])->assertOk();

        $this->postJson('/api/wizard/architecture', [
            'preferred_stack'    => 'Laravel+Blade',
            'hosting_preference' => 'WSL',
            'known_integrations' => [],
            'data_sensitivity'   => 'Confidential',
        ])->assertOk();
    }
}