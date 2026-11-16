<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Additive: preserve existing drafts and evidence; old records have unknown authors / pending review. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_claims', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('brand_knowledge_sources', function (Blueprint $table): void {
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreign('verified_by_user_id', 'fk_brand_source_verifier')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::table('brand_claim_evidence', function (Blueprint $table): void {
            $table->string('review_state', 20)->default('PENDING');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->index(['organisation_id', 'brand_id', 'claim_id', 'review_state'], 'idx_claim_evidence_review_state');
        });
        Schema::table('brand_approval_policies', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            // Nullable generated marker permits unlimited drafts/retired policies but exactly one active policy.
            $table->unsignedTinyInteger('active_marker')
                ->storedAs("CASE WHEN state = 'ACTIVE' THEN 1 ELSE NULL END");
            $table->unique(['organisation_id', 'brand_id', 'active_marker'], 'uq_one_active_approval_policy');
        });
        Schema::create('brand_claim_review_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('claim_id')->constrained('brand_claims')->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('rationale');
            $table->char('claim_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_review_tenant');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_review_brand_scope')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'claim_id'], 'fk_brand_review_claim_scope')
                ->references(['organisation_id', 'brand_id', 'id'])->on('brand_claims')->restrictOnDelete();
            $table->index(['organisation_id', 'brand_id', 'claim_id', 'created_at'], 'idx_claim_review_history');
        });
        Schema::create('brand_claim_review_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('review_event_id')->constrained('brand_claim_review_events')->restrictOnDelete();
            $table->foreignId('claim_evidence_id')->constrained('brand_claim_evidence')->restrictOnDelete();
            $table->char('source_content_hash', 64);
            $table->char('excerpt_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['organisation_id', 'brand_id', 'review_event_id', 'claim_evidence_id'], 'uq_review_evidence_once');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_review_evidence_brand_scope')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'review_event_id'], 'fk_review_evidence_event_scope')
                ->references(['organisation_id', 'brand_id', 'id'])->on('brand_claim_review_events')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'claim_evidence_id'], 'fk_review_evidence_claim_scope')
                ->references(['organisation_id', 'brand_id', 'id'])->on('brand_claim_evidence')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_claim_review_evidence');
        Schema::dropIfExists('brand_claim_review_events');
        Schema::table('brand_approval_policies', function (Blueprint $table): void {
            $table->dropUnique('uq_one_active_approval_policy');
            $table->dropColumn('active_marker');
            $table->dropConstrainedForeignId('activated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
        Schema::table('brand_claim_evidence', function (Blueprint $table): void {
            $table->dropIndex('idx_claim_evidence_review_state');
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn('review_state');
        });
        Schema::table('brand_knowledge_sources', function (Blueprint $table): void {
            $table->dropForeign('fk_brand_source_verifier');
            $table->dropConstrainedForeignId('submitted_by_user_id');
        });
        Schema::table('brand_claims', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
