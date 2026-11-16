<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_agent_delegations', function (Blueprint $t): void {
            $t->unique(['id', 'organisation_id', 'brand_id'], 'uq_agent_grant_scope_fk');
        });
        Schema::create('planner_agent_autonomous_runs', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $t->unsignedBigInteger('brand_id');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_autonomy_brand')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->unsignedBigInteger('delegation_id');
            $t->foreign(['delegation_id', 'organisation_id', 'brand_id'], 'fk_autonomy_grant_scope')
                ->references(['id', 'organisation_id', 'brand_id'])->on('planner_agent_delegations')->restrictOnDelete();
            $t->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 96);
            $t->char('request_hash', 64);
            $t->string('title', 180);
            $t->text('objective');
            $t->string('resource_kind', 24);
            $t->string('status', 32)->default('QUEUED_RESEARCH');
            $t->unsignedTinyInteger('research_attempts')->default(0);
            $t->unsignedTinyInteger('resource_attempts')->default(0);
            $t->uuid('lease_token')->nullable();
            $t->timestamp('lease_expires_at')->nullable();
            $t->text('findings')->nullable();
            $t->json('sources')->nullable();
            $t->char('research_hash', 64)->nullable();
            $t->longText('resource_text')->nullable();
            $t->char('resource_hash', 64)->nullable();
            $t->string('resource_model', 100)->nullable();
            $t->uuid('work_order_uuid')->nullable();
            $t->uuid('content_uuid')->nullable();
            $t->string('failure_code', 48)->nullable();
            $t->timestamps();
            $t->unique(['delegation_id', 'idempotency_key'], 'uq_autonomy_request_key');
            $t->index(['status', 'lease_expires_at', 'id'], 'idx_autonomy_ready');
            $t->index(['organisation_id', 'brand_id', 'created_at'], 'idx_autonomy_tenant');
        });
        Schema::create('planner_agent_autonomous_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('run_id')->constrained('planner_agent_autonomous_runs')->cascadeOnDelete();
            $t->string('event', 48);
            $t->json('evidence');
            $t->timestamp('created_at');
            $t->index(['run_id', 'id'], 'idx_autonomy_events');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_agent_autonomous_events');
        Schema::dropIfExists('planner_agent_autonomous_runs');
        Schema::table('planner_agent_delegations', function (Blueprint $t): void {
            $t->dropUnique('uq_agent_grant_scope_fk');
        });
    }
};
