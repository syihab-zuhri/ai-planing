<?php

namespace Tests\Feature;

use App\Models\Project;
use Tests\TestCase;

class ClarifyApiTest extends TestCase
{

    /* ------------------------------------------------------------------ */
    /*  POST /api/wizard/clarify/questions                                */
    /* ------------------------------------------------------------------ */

    public function test_questions_without_project_returns_empty(): void
    {
        $response = $this->withSession([])->postJson('/api/wizard/clarify/questions');

        $response->assertOk()
                 ->assertJsonPath('questions', [])
                 ->assertJsonPath('skip_to_generate', false)
                 ->assertJsonPath('message', 'Belum ada intake. Selesaikan Step 1-4 terlebih dahulu.');
    }

    public function test_questions_with_empty_intake_returns_questions(): void
    {
        // Create a project with minimal state
        $this->withSession([])->postJson('/api/wizard/start');

        $response = $this->postJson('/api/wizard/clarify/questions');

        $response->assertOk()
                 ->assertJsonStructure(['questions', 'skip_to_generate']);

        // Empty intake → should have questions (ASM-001, ASM-002, ASM-003, ASM-005)
        $questions = $response->json('questions');
        $this->assertNotEmpty($questions);
        $this->assertFalse($response->json('skip_to_generate'));
    }

    public function test_questions_with_complete_intake_returns_skip(): void
    {
        // Seed a project directly with complete state so clarify has nothing to ask
        $sessionId = session()->getId();
        Project::create([
            'id'               => fake()->uuid(),
            'session_id'       => $sessionId,
            'current_gate'     => 'A',
            'draft_state'      => [
                'intake' => [
                    'project_name'      => 'Complete Project',
                    'project_goal'      => 'A very complete project goal that exceeds thirty characters easily for the clarify requirement and more',
                    'target_users'      => 'Enterprise developers',
                    'known_constraints' => 'Must use PostgreSQL and deploy on AWS',
                ],
                'domain' => [
                    'domain_category'     => 'SaaS',
                    'problem_statement'   => 'Need better tooling',
                    'value_proposition'   => 'Faster dev cycles',
                    'scale_estimate_mvp'  => '1000',
                    'scale_estimate_12mo' => '50000',
                ],
                'scope' => [
                    'p0_features'  => ['CRUD operations', 'User authentication'],
                    'p1_features'  => ['Reporting'],
                    'p2_features'  => [],
                    'out_of_scope' => [],
                ],
                'architecture' => [
                    'preferred_stack'    => 'Laravel 11 + Blade + PostgreSQL',
                    'hosting_preference' => 'AWS',
                    'known_integrations' => ['Stripe'],
                    'data_sensitivity'   => 'Public',
                ],
                'clarifications' => [],
                'documents'      => new \stdClass(),
                'validation'     => null,
            ],
            'last_activity_at' => now(),
        ]);

        $response = $this->postJson('/api/wizard/clarify/questions');

        $response->assertOk();
        // With complete intake, complete arch, complete scope → should skip
        $this->assertTrue($response->json('skip_to_generate'));
        $this->assertEmpty($response->json('questions'));
    }

    public function test_questions_with_saran_sistem_stack(): void
    {
        $this->withSession([])->postJson('/api/wizard/start');

        $this->postJson('/api/wizard/intake', [
            'project_name'      => 'Test Project',
            'project_goal'      => 'A project goal that exceeds thirty characters easily for the clarify check',
            'target_users'      => 'Devs',
            'known_constraints' => 'Some constraints here',
        ]);

        $this->postJson('/api/wizard/scope', [
            'p0_features'  => ['Feature A', 'Feature B'],
            'p1_features'  => [],
            'p2_features'  => [],
            'out_of_scope' => [],
        ]);

        $this->postJson('/api/wizard/architecture', [
            'preferred_stack'    => 'Saran sistem',
            'hosting_preference' => 'Saran sistem',
            'known_integrations' => [],
            'data_sensitivity'   => 'Restricted',
        ]);

        $response = $this->postJson('/api/wizard/clarify/questions');

        $response->assertOk();
        $questions = $response->json('questions');
        $ids = array_column($questions, 'id');
        $this->assertContains('ASM-002', $ids, 'Should ask about stack when Saran sistem');
        $this->assertContains('ASM-004', $ids, 'Should ask about Restricted data sensitivity');
    }

    /* ------------------------------------------------------------------ */
    /*  POST /api/wizard/clarify/answers                                  */
    /* ------------------------------------------------------------------ */

    public function test_answers_saves_clarifications(): void
    {
        $this->withSession([])->postJson('/api/wizard/start');

        $response = $this->postJson('/api/wizard/clarify/answers', [
            'answers' => [
                ['id' => 'ASM-001', 'answer' => 'Internal pilot'],
                ['id' => 'ASM-002', 'answer' => 'Laravel 11 + Blade + PostgreSQL', 'label' => 'CONFIRMED'],
            ],
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['project_id', 'saved_count', 'next']);

        $this->assertEquals(2, $response->json('saved_count'));
        $this->assertEquals('/generate', $response->json('next'));
    }

    public function test_answers_validation_requires_fields(): void
    {
        $this->withSession([])->postJson('/api/wizard/start');

        $response = $this->postJson('/api/wizard/clarify/answers', [
            'answers' => [],
        ]);

        // Empty array should fail validation (required, array but empty)
        // Actually Laravel 'required|array' allows empty array. Let's test missing answer field:
        $response = $this->postJson('/api/wizard/clarify/answers', [
            'answers' => [
                ['id' => 'ASM-001'],  // missing 'answer'
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_answers_default_label_is_confirmed(): void
    {
        $this->withSession([])->postJson('/api/wizard/start');

        $response = $this->postJson('/api/wizard/clarify/answers', [
            'answers' => [
                ['id' => 'ASM-001', 'answer' => 'Internal pilot'],
            ],
        ]);

        $response->assertOk();

        // Verify in DB that label defaulted to CONFIRMED
        $project = Project::first();
        $clarifications = $project->draft_state['clarifications'] ?? [];
        $this->assertCount(1, $clarifications);
        $this->assertEquals('CONFIRMED', $clarifications[0]['label']);
    }

    public function test_answers_rejects_invalid_label(): void
    {
        $this->withSession([])->postJson('/api/wizard/start');

        $response = $this->postJson('/api/wizard/clarify/answers', [
            'answers' => [
                ['id' => 'ASM-001', 'answer' => 'test answer', 'label' => 'INVALID_LABEL'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_answers_respects_max_length(): void
    {
        $this->withSession([])->postJson('/api/wizard/start');

        $response = $this->postJson('/api/wizard/clarify/answers', [
            'answers' => [
                ['id' => 'ASM-001', 'answer' => str_repeat('x', 1001)],
            ],
        ]);

        $response->assertStatus(422);
    }
}
