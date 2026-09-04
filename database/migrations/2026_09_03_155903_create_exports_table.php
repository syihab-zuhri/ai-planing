<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ERD §4 — exports
        Schema::create('exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->default(0);
            $table->string('download_token', 64);
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->unique('download_token');
        });

        DB::statement("ALTER TABLE exports ADD CONSTRAINT exports_project_id_fk FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE");
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};