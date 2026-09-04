<?php

namespace Tests\Feature;

use App\Http\Controllers\GenerateController;
use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\AiResponse;
use App\Services\Ai\MockAiProvider;
use Tests\TestCase;

/**
 * GenerateStreamSseTest — SSE endpoint /api/generate/stream feature tests.
 *
 * Validates:
 *   1. StreamedResponse with Content-Type: text/event-stream
 *   2. SSE format: "event: xxx\ndata: {json}\n\n"
 *   3. Event types: 'progress' per document, 'complete' at end, 'error' on failure
 *   4. Proper data payloads (doc_id, status, current, total)
 *   5. Gate B set on all-success
 *   6. Error responses for missing project or incomplete wizard
 */
class GenerateStreamSseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $mock = new MockAiProvider();
        $mock->mockContent = "# Test Doc\n\nMock content " . str_repeat('x', 250);
        $this->app->instance(AiProviderInterface::class, $mock);
    }

    /**
     * GET /api/generate/stream returns Content-Type: text/event-stream.
     */
    public function test_stream_returns_correct_content_type(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
        $response->assertHeader('Cache-Control', 'no-cache, private');
    }

    /**
     * SSE body contains standard-format progress events followed by a complete event.
     */
    public function test_stream_emits_progress_and_complete_events(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $response->assertOk();

        $body = $response->streamedContent();
        $events = $this->parseSseEvents($body);

        // With mock (all succeed): 23 progress events + 1 complete event.
        $progressEvents = array_filter($events, fn ($e) => $e['event'] === 'progress');
        $completeEvents = array_filter($events, fn ($e) => $e['event'] === 'complete');

        $docCount = count(GenerateController::DOCUMENT_IDS);

        $this->assertCount($docCount, $progressEvents);
        $this->assertCount(1, $completeEvents);
    }

    /**
     * Each progress event has SSE format "event: progress\ndata: {json}\n\n".
     */
    public function test_stream_sse_format_is_standard(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $body = $response->streamedContent();

        // Verify SSE format: every event block must start with "event: "
        // followed by "data: " on the next line.
        $this->assertMatchesRegularExpression(
            '/event: progress\ndata: \{.*\}\n\n/',
            $body
        );
        $this->assertMatchesRegularExpression(
            '/event: complete\ndata: \{.*\}\n\n/',
            $body
        );
    }

    /**
     * Progress events contain doc_id, status, current, total fields.
     */
    public function test_progress_event_has_required_fields(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $body = $response->streamedContent();
        $events = $this->parseSseEvents($body);

        $firstProgress = collect($events)->first(fn ($e) => $e['event'] === 'progress');
        $this->assertNotNull($firstProgress);

        $data = $firstProgress['data'];
        $this->assertArrayHasKey('doc_id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertSame(1, $data['current']);
        $this->assertSame(count(GenerateController::DOCUMENT_IDS), $data['total']);
    }

    /**
     * Complete event has project_id, total, done, failed fields.
     */
    public function test_complete_event_has_required_fields(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $body = $response->streamedContent();
        $events = $this->parseSseEvents($body);

        $complete = collect($events)->first(fn ($e) => $e['event'] === 'complete');
        $this->assertNotNull($complete);

        $data = $complete['data'];
        $this->assertArrayHasKey('project_id', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('done', $data);
        $this->assertArrayHasKey('failed', $data);
        $this->assertSame(count(GenerateController::DOCUMENT_IDS), $data['total']);
        $this->assertSame(count(GenerateController::DOCUMENT_IDS), $data['done']);
        $this->assertSame(0, $data['failed']);
    }

    /**
     * Stream with all mock docs succeeding sets Gate B.
     */
    public function test_stream_sets_gate_b_on_all_success(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $response->assertOk();
        $response->streamedContent(); // Trigger the streaming callback.

        $project = Project::first();
        $project->refresh();
        $this->assertContains($project->current_gate, ['B', 'C', 'D']);
    }

    /**
     * Stream creates ai_job records for every document.
     */
    public function test_stream_creates_ai_jobs(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $response->assertOk();
        $response->streamedContent(); // Trigger the streaming callback.

        $project = Project::first();
        $jobCount = AiJob::where('project_id', $project->id)->count();
        $this->assertSame(count(GenerateController::DOCUMENT_IDS), $jobCount);
    }

    /**
     * Stream saves generated documents to draft_state.
     */
    public function test_stream_saves_documents_to_draft_state(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $response->assertOk();
        $response->streamedContent(); // Trigger the streaming callback.

        $project = Project::first();
        $project->refresh();
        $this->assertNotEmpty($project->draft_state['documents']);
        $this->assertArrayHasKey('PLANNING.md', $project->draft_state['documents']);
    }

    /**
     * Progress events have incrementing current counter.
     */
    public function test_progress_events_increment_current(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $body = $response->streamedContent();
        $events = $this->parseSseEvents($body);

        $progressEvents = array_values(
            array_filter($events, fn ($e) => $e['event'] === 'progress')
        );

        $docCount = count(GenerateController::DOCUMENT_IDS);
        $this->assertCount($docCount, $progressEvents);

        // current should increment from 1 to docCount.
        for ($i = 0; $i < $docCount; $i++) {
            $this->assertSame($i + 1, $progressEvents[$i]['data']['current']);
        }
    }

    /**
     * All DOCUMENT_IDS appear as doc_id in progress or error events.
     */
    public function test_all_document_ids_appear_in_events(): void
    {
        $this->seedMinimalProject();

        $response = $this->get('/api/generate/stream');
        $body = $response->streamedContent();
        $events = $this->parseSseEvents($body);

        $docIds = collect($events)
            ->filter(fn ($e) => in_array($e['event'], ['progress', 'error']))
            ->pluck('data.doc_id')
            ->toArray();

        foreach (GenerateController::DOCUMENT_IDS as $expectedId) {
            $this->assertContains($expectedId, $docIds, "Missing doc_id: {$expectedId}");
        }
    }

    /**
     * GET /api/generate/stream without a project → 404.
     */
    public function test_stream_without_project_returns_404(): void
    {
        $this->getJson('/api/generate/stream')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * GET /api/generate/stream with empty intake → 422.
     */
    public function test_stream_without_intake_returns_422(): void
    {
        // Create a project with empty intake.
        $this->postJson('/api/wizard/start');
        // At this point the project has no intake.project_name.

        $this->getJson('/api/generate/stream')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * Error event is emitted when a document generation fails.
     */
    public function test_stream_emits_error_event_on_failure(): void
    {
        $this->seedMinimalProject();

        // Replace provider with one that throws on the second call.
        $failingMock = new class extends MockAiProvider {
            private int $callCount = 0;

            public function chat(array $messages, array $options = []): AiResponse
            {
                $this->callCount++;
                if ($this->callCount === 2) {
                    throw new \RuntimeException('Mock generation failure');
                }
                return parent::chat($messages, $options);
            }
        };
        $failingMock->mockContent = "# Test Doc\n\n" . str_repeat('x', 250);
        $this->app->instance(AiProviderInterface::class, $failingMock);

        $response = $this->get('/api/generate/stream');
        $response->assertOk();

        $body = $response->streamedContent();
        $events = $this->parseSseEvents($body);

        $errorEvents = array_filter($events, fn ($e) => $e['event'] === 'error');
        $this->assertNotEmpty($errorEvents, 'Expected at least one error event');

        $errorEvent = array_values($errorEvents)[0];
        $this->assertArrayHasKey('code', $errorEvent['data']);
        $this->assertArrayHasKey('message', $errorEvent['data']);
        $this->assertSame('GENERATION_FAILED', $errorEvent['data']['code']);

        // Complete event should report at least 1 failed.
        $complete = collect($events)->first(fn ($e) => $e['event'] === 'complete');
        $this->assertNotNull($complete);
        $this->assertGreaterThan(0, $complete['data']['failed']);
    }

    /* -------------------------------------------------------------- */
    /*  Helpers                                                       */
    /* -------------------------------------------------------------- */

    /**
     * Parse raw SSE output into array of ['event' => string, 'data' => array].
     */
    private function parseSseEvents(string $body): array
    {
        $events = [];
        $blocks = preg_split('/\n\n/', trim($body));

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            $event = null;
            $data = null;

            foreach (explode("\n", $block) as $line) {
                if (str_starts_with($line, 'event: ')) {
                    $event = substr($line, 7);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data = json_decode(substr($line, 6), true);
                }
            }

            if ($event !== null && $data !== null) {
                $events[] = ['event' => $event, 'data' => $data];
            }
        }

        return $events;
    }

    private function seedMinimalProject(): void
    {
        $this->postJson('/api/wizard/intake', [
            'project_name' => 'SSE-Test',
            'project_goal' => 'A simple project to test SSE streaming endpoint.',
            'target_users' => 'Developers testing the streaming API.',
            'known_constraints' => null,
        ])->assertOk();

        $this->postJson('/api/wizard/domain', [
            'domain_category'     => 'API',
            'problem_statement'   => 'Need real-time generation progress feedback.',
            'value_proposition'   => 'Live progress updates via SSE.',
            'scale_estimate_mvp'  => '<100',
            'scale_estimate_12mo' => '100-1k',
        ])->assertOk();

        $this->postJson('/api/wizard/scope', [
            'p0_features'  => ['Generate', 'Stream'],
            'p1_features'  => [],
            'p2_features'  => [],
            'out_of_scope' => [],
        ])->assertOk();

        $this->postJson('/api/wizard/architecture', [
            'preferred_stack'    => 'Laravel+Blade',
            'hosting_preference' => 'WSL',
            'known_integrations' => [],
            'data_sensitivity'   => 'Internal',
        ])->assertOk();
    }
}
