<?php

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
        Schema::create('publication_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('publication_job_id');

            $table->string('from_state', 20)->nullable();
            $table->string('to_state', 20);
            $table->string('event_type', 50);
            $table->string('actor_type', 30);
            $table->string('actor_ref')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->json('details');

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('publication_job_id')->references('id')->on('publication_jobs')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'publication_job_id', 'occurred_at'], 'idx_publication_events');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_publication_events_brand_id_b43a8277');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_publication_events_brand_fa442892')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_publication_events_publication_job_i_73158743')->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_events');
    }
};
