<?php

namespace Tests\Feature;

use App\Models\Project;
use Tests\TestCase;

class SessionContinuityTest extends TestCase
{
    public function test_api_requests_share_one_session_project(): void
    {
        $this->postJson('/api/wizard/start')->assertOk();
        $this->postJson('/api/wizard/intake', [
            'project_name' => 'Continuity',
            'project_goal' => 'Verify API session continuity across requests.',
            'target_users' => 'QA team',
            'known_constraints' => null,
        ])->assertOk();

        $sessionIds = Project::pluck('session_id')->all();
        $this->assertSame(1, count($sessionIds), json_encode($sessionIds));
        $this->assertSame('Continuity', Project::first()->draft_state['intake']['project_name']);
        $this->getJson('/api/wizard/state')->assertJsonPath('draft_state.intake.project_name', 'Continuity');
    }
}
