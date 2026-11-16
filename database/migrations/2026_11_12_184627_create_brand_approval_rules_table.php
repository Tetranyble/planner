<?php

use App\Enums\ApprovalActionKind;
use App\Enums\ContentKind;
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
        Schema::create('brand_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('policy_id');

            $table->string('rule_name');
            $table->unsignedInteger('priority');
            $table->string('action_kind', 30)->default(ApprovalActionKind::EDIT_SCHEDULED->value);      // ApprovalActionKind
            $table->string('platform')->nullable();
            $table->string('content_kind', 20)->nullable(); // ContentKind
            $table->boolean('ai_generated')->nullable();
            $table->boolean('includes_sensitive_claim')->nullable();
            $table->boolean('requires_human_approval')->default(true);
            $table->unsignedSmallInteger('required_approvals')->nullable();
            $table->boolean('forbid_self_approval')->default(false);
            $table->boolean('is_enabled')->default(true);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('policy_id')->references('id')->on('brand_approval_policies')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'policy_id', 'priority'], 'uq_brand_approval');

            $table->index(
                ['organisation_id', 'brand_id', 'policy_id', 'priority'],
                'idx_brand_approval_rules'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_approval_rul_brand_id_08f54158');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_approval_rul_brand_d82eb8c9')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'policy_id'], 'fk_brand_approval_rul_policy_id_460eb2a3')->references(['organisation_id', 'brand_id', 'id'])->on('brand_approval_policies')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_approval_rules');
    }
};
