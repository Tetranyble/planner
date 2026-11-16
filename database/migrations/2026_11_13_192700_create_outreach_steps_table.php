<?php

use App\Enums\Growth\OutreachStepChannel;
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
        Schema::create('outreach_steps', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('outreach_sequence_id');

            $table->unsignedInteger('step_number');
            $table->unsignedInteger('delay_minutes');
            $table->string('channel', 20)->default(OutreachStepChannel::EMAIL->value);
            $table->text('subject_template')->nullable();
            $table->text('body_template');
            $table->boolean('requires_approval')->default(true);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('outreach_sequence_id')->references('id')->on('outreach_sequences')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'outreach_sequence_id', 'step_number'], 'uq_outreach_steps_number'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_outreach_steps_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_outreach_steps_brand_152dfcdb')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'outreach_sequence_id'], 'fk_outreach_steps_outreach_sequence_12344d2c')->references(['organisation_id', 'brand_id', 'id'])->on('outreach_sequences')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outreach_steps');
    }
};
