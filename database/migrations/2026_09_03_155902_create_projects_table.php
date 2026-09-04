<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ERD §2 — projects
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id', 64);
            $table->jsonb('draft_state')->default(DB::raw("'{}'::jsonb"));
            $table->string('current_gate', 2)->default('A');
            $table->timestampTz('last_activity_at')->useCurrent();
            $table->timestampsTz();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unique('session_id');
            $table->index('last_activity_at');
        });

        // ERD §2 — gate CHECK
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_current_gate_check CHECK (current_gate IN ('A','B','C','D'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};