<?php

use App\Enums\Growth\OutreachDispatchState;
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
        Schema::create('outreach_dispatches', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('outreach_enrollment_id');
            $table->unsignedBigInteger('outreach_step_id');

            $table->string('dispatch_key');
            $table->string('state', 20)->default(OutreachDispatchState::PENDING_APPROVAL->value);
            $table->string('provider_message_id')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('error_code')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('outreach_enrollment_id')->references('id')->on('outreach_enrollments')
                ->onDelete('restrict');
            $table->foreign('outreach_step_id')->references('id')->on('outreach_steps')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'dispatch_key'], 'uq_outreach_dispatches_key');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_outreach_dispatches_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_outreach_dispatche_brand_57242fa8')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'outreach_enrollment_id'], 'fk_outreach_dispatche_outreach_enrollme_b6297716')->references(['organisation_id', 'brand_id', 'id'])->on('outreach_enrollments')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'outreach_step_id'], 'fk_outreach_dispatche_outreach_step_id_f53a227f')->references(['organisation_id', 'brand_id', 'id'])->on('outreach_steps')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outreach_dispatches');
    }
};
