<?php

use App\Enums\Growth\OutreachEnrollmentState;
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
        Schema::create('outreach_enrollments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('outreach_sequence_id');
            $table->unsignedBigInteger('lead_contact_id');

            $table->string('state', 20)->default(OutreachEnrollmentState::ACTIVE->value);
            $table->unsignedInteger('next_step_number')->default(1);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('outreach_sequence_id')->references('id')->on('outreach_sequences')
                ->onDelete('restrict');
            $table->foreign('lead_contact_id')->references('id')->on('lead_contacts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'outreach_sequence_id', 'lead_contact_id'],
                'uq_outreach_enroll_pair'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_outreach_enroll_brand_id');

            // Replaces partial index idx_outreach_enrollments_due
            // (state, next_run_at) WHERE state='active'.
            $table->unsignedTinyInteger('active_marker')
                ->storedAs("CASE WHEN state = 'ACTIVE' THEN 1 ELSE NULL END")
                ->nullable();
            $table->index(['active_marker', 'next_run_at'], 'idx_outreach_enroll_due'
            );
            $table->foreign(['organisation_id', 'brand_id'], 'fk_outreach_enrollmen_brand_9d32890d')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'outreach_sequence_id'], 'fk_outreach_enrollmen_outreach_sequence_6ba03a8e')->references(['organisation_id', 'brand_id', 'id'])->on('outreach_sequences')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_contact_id'], 'fk_outreach_enrollmen_lead_contact_id_9e2ef1f2')->references(['organisation_id', 'brand_id', 'id'])->on('lead_contacts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outreach_enrollments');
    }
};
