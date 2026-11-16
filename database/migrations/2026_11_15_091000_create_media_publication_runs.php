<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_publication_runs', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('media_publication_preparation_id');
            $t->unsignedBigInteger('publication_job_id');
            $t->unsignedBigInteger('armed_by_user_id')->nullable();
            $t->string('provider', 20);
            $t->string('state', 32)->default('HELD');
            $t->string('next_operation', 48);
            $t->unsignedInteger('stage_sequence')->default(0);
            $t->unsignedInteger('poll_count')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->string('lease_owner', 80)->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->timestamp('next_check_at')->nullable();
            // Laravel encrypted cast. Never put this field into an API resource, event or log.
            $t->text('provider_operation_reference')->nullable();
            $t->text('verified_post_reference')->nullable();
            $t->string('blocker_code', 70)->nullable();
            $t->unique(['organisation_id', 'media_publication_preparation_id'], 'uq_media_run_preparation');
            $t->unique(['organisation_id', 'publication_job_id'], 'uq_media_run_job');
            $t->unique(['organisation_id', 'brand_id', 'id'], 'uq_media_run_tenant');
            $t->index(['state', 'next_check_at', 'id'], 'idx_media_run_due');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_media_run_brand')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'media_publication_preparation_id'], 'fk_media_run_preparation')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_publication_preparations')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_media_run_job')
                ->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();
            $t->foreign('armed_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('media_publication_stage_attempts', function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('media_publication_run_id');
            $t->unsignedInteger('stage_sequence');
            $t->string('operation', 48);
            $t->string('state', 32)->default('STARTED');
            $t->string('outcome_code', 70)->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unique(['media_publication_run_id', 'stage_sequence'], 'uq_media_stage_sequence');
            $t->foreign(['organisation_id', 'brand_id', 'media_publication_run_id'], 'fk_media_stage_run')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_publication_runs')->restrictOnDelete();
        });
        Schema::create('media_publication_run_events', function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('media_publication_run_id');
            $t->string('action', 50);
            $t->string('from_state', 32)->nullable();
            $t->string('to_state', 32);
            $t->unsignedBigInteger('actor_user_id')->nullable();
            $t->json('details')->nullable();
            $t->index(['organisation_id', 'brand_id', 'media_publication_run_id'], 'idx_media_run_evidence');
            $t->foreign(['organisation_id', 'brand_id', 'media_publication_run_id'], 'fk_media_run_event_parent')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_publication_runs')->restrictOnDelete();
            $t->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publication_run_events');
        Schema::dropIfExists('media_publication_stage_attempts');
        Schema::dropIfExists('media_publication_runs');
    }
};
