<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ERD §3 — ai_jobs
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('doc_id', 100);
            $table->string('provider', 50);
            $table->string('status', 20)->default('queued');
            $table->integer('token_in')->nullable();
            $table->integer('token_out')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->index('project_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE ai_jobs ADD CONSTRAINT ai_jobs_project_id_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE");
        DB::statement("ALTER TABLE ai_jobs ADD CONSTRAINT ai_jobs_status_check CHECK (status IN ('queued','running','done','failed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};