<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_reconciliation_evidence', function (Blueprint $t) {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('publication_job_id');
            $t->unsignedBigInteger('submitted_by');
            $t->string('evidence_kind', 40);
            $t->string('external_reference', 255)->nullable();
            $t->char('evidence_sha256', 64);
            $t->text('summary');
            $t->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_pub_rec_ev_job')
                ->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();
            $t->foreign('submitted_by')->references('id')->on('users')->restrictOnDelete();
            $t->index(['organisation_id', 'brand_id', 'publication_job_id'], 'idx_pub_rec_ev_scope');
            $t->unique(['organisation_id', 'brand_id', 'publication_job_id', 'id'], 'uq_pub_rec_evidence_scoped');
        });
        Schema::create('publication_reconciliation_decisions', function (Blueprint $t) {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('publication_job_id');
            $t->unsignedBigInteger('reviewed_by');
            $t->unsignedBigInteger('evidence_id');
            $t->string('disposition', 40);
            $t->text('rationale');
            $t->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_pub_rec_dec_job')
                ->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();
            $t->foreign('reviewed_by')->references('id')->on('users')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'publication_job_id', 'evidence_id'], 'fk_pub_rec_dec_ev_scope')
                ->references(['organisation_id', 'brand_id', 'publication_job_id', 'id'])->on('publication_reconciliation_evidence')->restrictOnDelete();
            $t->unique('publication_job_id', 'uq_pub_rec_one_decision_per_job');
            $t->index(['organisation_id', 'brand_id', 'publication_job_id'], 'idx_pub_rec_dec_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_reconciliation_decisions');
        Schema::dropIfExists('publication_reconciliation_evidence');
    }
};
