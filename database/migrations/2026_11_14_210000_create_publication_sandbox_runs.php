<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_sandbox_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id');
            $t->foreignId('brand_id');
            $t->foreignId('publication_job_id');
            $t->string('operation_key', 128);
            $t->string('provider', 32);
            $t->string('scenario', 32);
            $t->string('status', 24)->default('QUEUED');
            $t->string('outcome_kind', 32)->nullable();
            $t->string('outcome_code', 64)->nullable();
            $t->json('safe_result')->nullable();
            $t->string('lease_token', 64)->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->unsignedInteger('attempt_count')->default(0);
            $t->timestamps();
            $t->unique(['organisation_id', 'publication_job_id', 'operation_key'], 'uq_sandbox_operation');
            $t->index(['status', 'lease_until', 'id'], 'idx_sandbox_work');
            $t->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_sandbox_parent')->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();
        });
        Schema::create('publication_sandbox_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('publication_sandbox_run_id');
            $t->string('event', 48);
            $t->json('safe_details')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('publication_sandbox_run_id', 'fk_sandbox_event_run')->references('id')->on('publication_sandbox_runs')->restrictOnDelete();
            $t->index(['publication_sandbox_run_id', 'id'], 'idx_sandbox_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_sandbox_events');
        Schema::dropIfExists('publication_sandbox_runs');
    }
};
