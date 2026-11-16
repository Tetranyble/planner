<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The composite key prevents an evidence/decision from being assigned to a
        // revocation request from another organisation at the database level.
        Schema::table('provider_grant_revocation_requests', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'id'], 'uq_grant_revocation_org_id');
        });

        Schema::create('provider_grant_reconciliation_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('revocation_request_id');
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('source_type', 32); // PROVIDER_SUPPORT | PROVIDER_CONSOLE | PROVIDER_RECEIPT | INVESTIGATION
            $table->string('reference', 512); // Opaque provider case/reference or vetted HTTPS link; never tokens
            $table->text('summary');
            $table->char('evidence_digest', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['organisation_id', 'revocation_request_id'], 'fk_grant_recon_evidence_request')
                ->references(['organisation_id', 'id'])->on('provider_grant_revocation_requests')->restrictOnDelete();
            $table->foreign('submitted_by_user_id', 'fk_grant_recon_evidence_submitter')
                ->references('id')->on('users')->restrictOnDelete();
            $table->index(['organisation_id', 'revocation_request_id', 'id'], 'idx_grant_recon_evidence_latest');
            $table->unique(['organisation_id', 'id'], 'uq_grant_recon_evidence_org_id');
            $table->unique(['organisation_id', 'revocation_request_id', 'id'], 'uq_grant_recon_evidence_scope');
        });

        Schema::create('provider_grant_reconciliation_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('revocation_request_id');
            $table->unsignedBigInteger('evidence_id');
            $table->unsignedBigInteger('reviewed_by_user_id');
            $table->string('outcome', 32); // ATTESTED_REVOKED | CONFIRMED_ACTIVE | ESCALATED
            $table->text('rationale');
            $table->char('evidence_digest', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['organisation_id', 'revocation_request_id'], 'fk_grant_recon_decision_request')
                ->references(['organisation_id', 'id'])->on('provider_grant_revocation_requests')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id', 'fk_grant_recon_decision_reviewer')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['organisation_id', 'revocation_request_id', 'evidence_id'], 'fk_grant_recon_decision_evidence')
                ->references(['organisation_id', 'revocation_request_id', 'id'])
                ->on('provider_grant_reconciliation_evidence')->restrictOnDelete();
            $table->unique('evidence_id', 'uq_grant_recon_decision_evidence');
            $table->index(['organisation_id', 'revocation_request_id', 'id'], 'idx_grant_recon_decisions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_grant_reconciliation_decisions');
        Schema::dropIfExists('provider_grant_reconciliation_evidence');
        Schema::table('provider_grant_revocation_requests', function (Blueprint $table): void {
            $table->dropUnique('uq_grant_revocation_org_id');
        });
    }
};
