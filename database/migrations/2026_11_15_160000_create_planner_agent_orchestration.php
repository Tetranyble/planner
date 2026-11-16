<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_agent_delegations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $t->unsignedBigInteger('brand_id');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_agent_delegation_brand')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('sanctum_token_id')->unique();
            $t->string('name', 100);
            $t->string('mode', 28);
            $t->json('account_uuids');
            $t->unsignedSmallInteger('daily_action_limit')->default(20);
            $t->unsignedTinyInteger('daily_arm_limit')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['organisation_id', 'brand_id', 'user_id', 'revoked_at'], 'idx_agent_delegation_scope');
        });
        Schema::create('planner_agent_work_orders', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $t->unsignedBigInteger('brand_id');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_agent_order_brand')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->foreignId('delegation_id')->constrained('planner_agent_delegations')->restrictOnDelete();
            $t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $t->string('title', 180);
            $t->text('objective');
            $t->json('sources');
            $t->text('findings');
            $t->char('research_hash', 64);
            $t->uuid('draft_content_uuid')->nullable();
            $t->string('status', 32)->default('RESEARCH_RECORDED');
            $t->timestamps();
            $t->index(['organisation_id', 'brand_id', 'created_at'], 'idx_agent_order_scope');
        });
        Schema::create('planner_agent_action_receipts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('delegation_id')->constrained('planner_agent_delegations')->restrictOnDelete();
            $t->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $t->unsignedBigInteger('brand_id');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_agent_receipt_brand')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->string('action', 40);
            $t->string('idempotency_key', 96);
            $t->char('input_hash', 64);
            $t->json('result');
            $t->timestamp('created_at');
            $t->unique(['delegation_id', 'idempotency_key'], 'uq_agent_delegation_idempotency');
            $t->index(['delegation_id', 'created_at', 'action'], 'idx_agent_daily_quota');
        });
        Schema::create('planner_agent_work_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('work_order_id')->constrained('planner_agent_work_orders')->cascadeOnDelete();
            $t->string('action', 40);
            $t->json('evidence');
            $t->timestamp('created_at');
            $t->index(['work_order_id', 'created_at'], 'idx_agent_work_events');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_agent_work_events');
        Schema::dropIfExists('planner_agent_action_receipts');
        Schema::dropIfExists('planner_agent_work_orders');
        Schema::dropIfExists('planner_agent_delegations');
    }
};
