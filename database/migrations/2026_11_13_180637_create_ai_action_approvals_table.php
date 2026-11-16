<?php

use App\Enums\Automation\AiActionApprovalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_action_approvals', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('ai_tool_execution_id');

            $table->string('action_type');
            $table->char('arguments_hash', 64);
            $table->string('status', 20)->default(AiActionApprovalStatus::PENDING->value);
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->text('reason')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('ai_tool_execution_id')->references('id')->on('ai_tool_executions')
                ->onDelete('restrict');

            $table->foreign('decided_by_user_id')->references('id')->on('users')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'brand_id', 'status', 'requested_at'],
                'idx_ai_action_approvals_pending');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_ai_action_approval_brand_id_6c4a8c1b');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_ai_action_approval_brand_d04b1e24')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'ai_tool_execution_id'], 'fk_ai_action_approval_ai_tool_execution_cf6ab5d3')->references(['organisation_id', 'brand_id', 'id'])->on('ai_tool_executions')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_action_approvals');
    }
};
