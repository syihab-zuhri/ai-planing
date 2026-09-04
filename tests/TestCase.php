<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base test case for BlueprintForge Phase 1.
 *
 * - Uses SQLite in-memory (configured in phpunit.xml + .env.testing).
 * - Uses an SQLite-compatible schema bootstrap instead of running the
 *   PostgreSQL-flavored migrations directly: the production migrations
 *   use `jsonb`, `::jsonb` casts, `ADD CONSTRAINT ... CHECK`, and
 *   `ADD CONSTRAINT ... FOREIGN KEY` which are Postgres-only syntax and
 *   cannot be replayed on SQLite without modifying the migration files.
 * - When the production migration files are made dialect-agnostic,
 *   delete `refreshTestDatabase()` and rely on `RefreshDatabase` directly.
 *
 * CACHE HANDLING:
 * The live service ships a cached `bootstrap/cache/config.php` that locks
 * `database.default` to `pgsql`. Laravel's `LoadEnvironmentVariables`
 * bootstrapper short-circuits when config is cached, ignoring
 * phpunit.xml's env vars. To make tests pick up the SQLite config, the
 * cache files are moved aside in `setUp()` (once per process) and
 * restored in `tearDown()` (also once per process). The first test
 * triggers the stash, the last test triggers the restore.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Cache files moved aside for the duration of the test run.
     * Restored on process shutdown via restoreConfigCache().
     *
     * @var array<string, string>
     */
    private static array $stashedCaches = [];

    /**
     * Process-level flag: only one tearDown should restore caches.
     */
    private static bool $cachesRestored = false;

    protected function setUp(): void
    {
        // Force sqlite in-memory for tests BEFORE Laravel reads any env.
        // Overrides both .env (which says pgsql) and cached config.
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        // Move cached config aside so Laravel re-reads env.
        $this->stashConfigCache();

        parent::setUp();

        // Laravel's in-process JSON client does not retain Set-Cookie
        // responses. Install one valid encrypted session cookie explicitly so
        // multi-request tests represent a same-origin browser session.
        $session = $this->app->make('session.store');
        $session->setId(str_repeat('a', 40));
        $session->start();
        $session->save();
        $this->withCookie(config('session.cookie'), $session->getId());
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        // RefreshDatabase will roll back between tests, but the in-memory
        // schema stays alive for the whole process so subsequent tests
        // can see it. Drop the test tables here so a re-run in the same
        // process starts from zero.
        $this->dropTestTables();

        parent::tearDown();

        // Caches are restored exclusively by the shutdown function
        // registered in stashConfigCache() so we don't pull the rug
        // out from under later tests in the same phpunit run.
    }

    /**
     * Override the trait's migration step. We build the schema directly
     * for SQLite, then mark the test database as fully migrated so
     * RefreshDatabase does not re-run anything.
     */
    public function refreshTestDatabase(): void
    {
        // SQLite bootstrap — schema-only, no constraints that Postgres
        // would otherwise add. Mirrors the production ERD (projects,
        // ai_jobs, exports) but expressed in SQLite-compatible DDL.
        $this->createSqliteSchema();

        // Record the bootstrap migration so RefreshDatabase knows the
        // schema is already in place and skips its default migrator.
        $this->artisan('migrate:install')->run();
        DB::table('migrations')->insert([
            'migration' => '2026_09_03_sqlite_test_schema_bootstrap',
            'batch' => 1,
        ]);
    }

    /**
     * Create the schema BlueprintForge tests need, using SQLite-compatible DDL.
     *
     * The tables mirror ERD.md but use plain `json` and `text` instead of
     * `jsonb`, and skip PostgreSQL CHECK constraints (the application code
     * enforces gate/status validation anyway).
     */
    protected function createSqliteSchema(): void
    {
        // Drop any leftover tables from a previous run.
        Schema::dropIfExists('exports');
        Schema::dropIfExists('ai_jobs');
        Schema::dropIfExists('projects');

        Schema::create('projects', function ($table) {
            $table->uuid('id')->primary();
            $table->string('session_id', 64);
            $table->json('draft_state');
            $table->string('current_gate', 2)->default('A');
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamps();
            $table->unique('session_id');
            $table->index('last_activity_at');
        });

        Schema::create('ai_jobs', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('doc_id', 100);
            $table->string('provider', 50);
            $table->string('status', 20)->default('queued');
            $table->integer('token_in')->nullable();
            $table->integer('token_out')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('project_id');
            $table->index('status');
        });

        Schema::create('exports', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->default(0);
            $table->string('download_token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique('download_token');
        });
    }

    /**
     * Move Laravel's cached config files aside so the framework
     * re-reads .env.testing + phpunit.xml env vars at boot. The
     * production config cache holds the live pgsql connection —
     * without this override, `env('DB_CONNECTION')` returns
     * 'sqlite' (from PHPUnit) but `config('database.default')`
     * still returns 'pgsql' (from the cache).
     */
    protected function stashConfigCache(): void
    {
        if (self::$stashedCaches !== []) {
            return; // Already stashed by a previous test in this run.
        }

        // We can't use base_path() here because the framework hasn't
        // booted yet. Derive the path relative to this test file.
        $cachePath = dirname(__DIR__) . '/bootstrap/cache';

        foreach (['config.php', 'packages.php'] as $file) {
            $source = "{$cachePath}/{$file}";
            $target = "{$cachePath}/{$file}.bak.test";

            if (file_exists($source) && !file_exists($target)) {
                @rename($source, $target);
                self::$stashedCaches[$source] = $target;
            }
        }

        // Register a process-exit hook to ensure caches come back even
        // if phpunit dies or is killed mid-run. This is a safety net;
        // the normal restore happens in restoreConfigCacheIfLast().
        if (self::$stashedCaches !== []) {
            register_shutdown_function(function () {
                if (!self::$cachesRestored) {
                    self::$cachesRestored = true;
                    foreach (self::$stashedCaches as $source => $target) {
                        if (file_exists($target) && !file_exists($source)) {
                            @rename($target, $source);
                        }
                    }
                }
            });
        }
    }

    /**
     * Restore the cached config from its stash. Called once at the
     * end of the run (PHPUnit runs tests sequentially in one process
     * by default; the shutdown hook above is the safety net).
     */
    protected function restoreConfigCacheIfLast(): void
    {
        if (self::$cachesRestored || self::$stashedCaches === []) {
            return;
        }

        self::$cachesRestored = true;
        foreach (self::$stashedCaches as $source => $target) {
            if (file_exists($target) && !file_exists($source)) {
                @rename($target, $source);
            }
        }
    }

    protected function dropTestTables(): void
    {
        try {
            Schema::dropIfExists('exports');
            Schema::dropIfExists('ai_jobs');
            Schema::dropIfExists('projects');
        } catch (\Throwable) {
            // Connection may already be closed; safe to ignore.
        }
    }
}