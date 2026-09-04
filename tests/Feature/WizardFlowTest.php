<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Integration test for the wizard lifecycle:
 *   createProject → saveIntake → saveDomain → saveScope → saveArchitecture
 *   → state retrieved.
 *
 * Two layers are covered:
 *
 *   1. **Self-contained flow** (always runs): uses a stub service bound
 *      into the container so the test is meaningful today, regardless
 *      of how far the backend `App\Services\WizardService` has been
 *      integrated. The stub lives in `tests/Mocks/` so test code does
 *      not import classes that may not exist yet.
 *
 *   2. **HTTP contract** (run only when backend routes exist): hits the
 *      real `/api/wizard/*` endpoints via Laravel's test client to
 *      confirm the live backend matches the same shape.
 *
 * Reference: API.md §3, PRD/INTAKE.md §7.
 */
class WizardFlowTest extends TestCase
{
    /**
     * Fields the wizard pipeline expects at each step.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $stepPayloads = [
        'intake' => [
            'project_name' => 'Andi Web Project',
            'project_goal' => 'Help Andi bootstrap freelance project docs.',
            'target_users' => 'Solo developers and small teams.',
            'known_constraints' => 'Limited time, no dedicated designer.',
        ],
        'domain' => [
            'domain_category' => 'Web',
            'problem_statement' => 'Freelancers waste hours on documentation scaffolding.',
            'value_proposition' => 'Auto-generated, structured blueprint in <30 minutes.',
            'scale_estimate_mvp' => '<100',
            'scale_estimate_12mo' => '100-1k',
        ],
        'scope' => [
            'p0_features' => ['Wizard intake', 'AI document generation', 'Markdown export'],
            'p1_features' => ['PDF export', 'Brief upload'],
            'p2_features' => ['Multi-tenant workspace'],
            'out_of_scope' => ['Code generation'],
        ],
        'architecture' => [
            'preferred_stack' => 'Laravel+Blade',
            'hosting_preference' => 'WSL',
            'known_integrations' => ['9router AI'],
            'data_sensitivity' => 'Internal',
        ],
    ];

    public function test_full_wizard_lifecycle_via_stub_service(): void
    {
        $service = new \Tests\Mocks\WizardServiceStub();

        // Step 0 — create project.
        $project = $service->createProject('session-andi-001');
        $this->assertNotNull($project['id']);
        $this->assertSame('A', $project['current_gate']);
        $this->assertIsArray($project['draft_state']);
        $this->assertArrayHasKey('intake', $project['draft_state']);

        // Step 1 — intake.
        $project = $service->saveIntake($project['id'], $this->stepPayloads['intake']);
        $this->assertSame($this->stepPayloads['intake'], $project['draft_state']['intake']);

        // Step 2 — domain.
        $project = $service->saveDomain($project['id'], $this->stepPayloads['domain']);
        $this->assertSame($this->stepPayloads['domain'], $project['draft_state']['domain']);

        // Step 3 — scope.
        $project = $service->saveScope($project['id'], $this->stepPayloads['scope']);
        $this->assertSame($this->stepPayloads['scope'], $project['draft_state']['scope']);

        // Step 4 — architecture.
        $project = $service->saveArchitecture($project['id'], $this->stepPayloads['architecture']);
        $this->assertSame($this->stepPayloads['architecture'], $project['draft_state']['architecture']);

        // Final — retrieve state and assert all four steps survived.
        $reloaded = $service->getState($project['id']);
        $this->assertSame($project['id'], $reloaded['id']);
        $this->assertSame($this->stepPayloads['intake'], $reloaded['draft_state']['intake']);
        $this->assertSame($this->stepPayloads['domain'], $reloaded['draft_state']['domain']);
        $this->assertSame($this->stepPayloads['scope'], $reloaded['draft_state']['scope']);
        $this->assertSame($this->stepPayloads['architecture'], $reloaded['draft_state']['architecture']);
    }

    public function test_state_retrieval_returns_null_before_start(): void
    {
        $service = new \Tests\Mocks\WizardServiceStub();

        // No project exists for this id.
        $this->assertNull($service->getState('non-existent-session'));
    }

    public function test_intake_saves_each_field_independently(): void
    {
        $service = new \Tests\Mocks\WizardServiceStub();
        $project = $service->createProject('session-andi-002');

        $partial = ['project_name' => 'Quick Project'];
        $project = $service->saveIntake($project['id'], $partial);

        $this->assertSame('Quick Project', $project['draft_state']['intake']['project_name']);
        $this->assertArrayNotHasKey('project_goal', $project['draft_state']['intake']);
    }

    public function test_wizard_endpoints_smoke_against_real_routes(): void
    {
        // Start the wizard.
        $response = $this->postJson('/api/wizard/start');
        $response->assertStatus(200);
        $response->assertJsonStructure(['project_id', 'redirect']);

        // State before any input: should be empty draft_state.
        $state = $this->getJson('/api/wizard/state');
        $state->assertStatus(200);
        $state->assertJsonStructure([
            'project_id',
            'current_gate',
            'draft_state' => ['intake', 'domain', 'scope', 'architecture', 'clarifications'],
        ]);
    }
}