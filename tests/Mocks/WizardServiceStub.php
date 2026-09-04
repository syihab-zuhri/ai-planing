<?php

namespace Tests\Mocks;

/**
 * Self-contained stub of the wizard service used by WizardFlowTest.
 *
 * Mirrors the API shape of `App\Services\WizardService`:
 *   - createProject(sessionId)
 *   - saveIntake / saveDomain / saveScope / saveArchitecture
 *   - getState
 *
 * State lives in-process (PHP array) so tests do not depend on the
 * SQLite test schema or on real backend services. When the production
 * `App\Services\WizardService` lands, the feature tests can either
 * keep using this stub or be re-pointed at the real class via a
 * container binding override.
 */
class WizardServiceStub
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $projects = [];

    public function createProject(string $sessionId): array
    {
        if (isset($this->projects[$sessionId])) {
            return $this->projects[$sessionId];
        }

        $this->projects[$sessionId] = [
            'id' => 'stub-' . substr(hash('sha256', $sessionId), 0, 12),
            'session_id' => $sessionId,
            'current_gate' => 'A',
            'draft_state' => [
                'intake' => null,
                'domain' => null,
                'scope' => null,
                'architecture' => null,
                'clarifications' => [],
            ],
        ];

        return $this->projects[$sessionId];
    }

    /**
     * Look up state by session_id or by project id. Returns null if
     * no project exists for either key — mirrors the production
     * behavior described in WizardController::state().
     */
    public function getState(string $sessionIdOrProjectId): ?array
    {
        if (isset($this->projects[$sessionIdOrProjectId])) {
            return $this->projects[$sessionIdOrProjectId];
        }

        foreach ($this->projects as $project) {
            if ($project['id'] === $sessionIdOrProjectId) {
                return $project;
            }
        }

        return null;
    }

    public function saveIntake(string $projectId, array $data): array
    {
        return $this->saveStep($projectId, 'intake', $data);
    }

    public function saveDomain(string $projectId, array $data): array
    {
        return $this->saveStep($projectId, 'domain', $data);
    }

    public function saveScope(string $projectId, array $data): array
    {
        return $this->saveStep($projectId, 'scope', $data);
    }

    public function saveArchitecture(string $projectId, array $data): array
    {
        return $this->saveStep($projectId, 'architecture', $data);
    }

    /**
     * Find the project by id (across all session buckets) and patch the
     * requested step. Each save merges new keys on top of whatever was
     * previously stored for that step, matching the production contract.
     */
    private function saveStep(string $projectId, string $step, array $data): array
    {
        foreach ($this->projects as $sessionId => $project) {
            if ($project['id'] !== $projectId) {
                continue;
            }

            $existing = $project['draft_state'][$step] ?? [];
            if (!is_array($existing)) {
                $existing = [];
            }

            $this->projects[$sessionId]['draft_state'][$step] = array_merge($existing, $data);

            return $this->projects[$sessionId];
        }

        throw new \RuntimeException("Project {$projectId} not found — call createProject() first.");
    }
}