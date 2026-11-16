<?php

use App\Enums\Publishing\PublicationJobState;
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
        Schema::create('publication_jobs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_target_id');
            $table->unsignedBigInteger('approval_request_id');

            $table->string('occurrence_key');
            $table->timestamp('run_at');
            $table->string('state', 20)->default(PublicationJobState::SCHEDULED->value);
            $table->string('lease_owner')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedBigInteger('version')->default(0);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign(['organisation_id', 'brand_id'])
                ->references(['organisation_id', 'id'])->on('brands')
                ->onDelete('restrict');

            $table->foreign('content_target_id')->references('id')->on('content_targets')
                ->onDelete('restrict');
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'content_target_id', 'occurrence_key'], 'uq_publication_occurrence');

            // Replaces the partial index idx_publication_jobs_due
            // (WHERE state IN ('scheduled','retry_wait')).
            // Generated column is set only for the two active states,
            // giving us an effective filter without partial-index support.
            $table->unsignedTinyInteger('due_state_marker')
                ->storedAs("CASE WHEN state IN ('SCHEDULED','RETRY_WAIT') THEN 1 ELSE NULL END")
                ->nullable();
            $table->index(
                ['due_state_marker', 'state', 'run_at'],
                'idx_publication_jobs_due_state'
            );

            // Replaces the partial index idx_publication_jobs_leases
            // (WHERE state IN ('leased','publishing')).
            $table->unsignedTinyInteger('lease_state_marker')
                ->storedAs("CASE WHEN state IN ('LEASED','PUBLISHING') THEN 1 ELSE NULL END")
                ->nullable();
            $table->index(['lease_state_marker', 'lease_until'],
                'idx_publication_lease_state'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_publication_jobs_brand_id_06a41d01');
            $table->foreign(['organisation_id', 'brand_id', 'content_target_id'], 'fk_publication_jobs_content_target_id_af5beebc')->references(['organisation_id', 'brand_id', 'id'])->on('content_targets')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'approval_request_id'], 'fk_publication_jobs_approval_request__db759195')->references(['organisation_id', 'brand_id', 'id'])->on('approval_requests')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_jobs');
    }
};
