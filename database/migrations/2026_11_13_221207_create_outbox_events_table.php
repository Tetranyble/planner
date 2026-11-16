<?php

use App\Enums\Operation\OutboxEventState;
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
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type', 50);
            $table->string('event_key');
            $table->json('payload');
            $table->string('state', 20)->default(OutboxEventState::PENDING->value);
            $table->string('lease_owner')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'event_key'], 'uq_outbox_events_key');

            // Replaces partial index idx_outbox_events_due
            // (state, COALESCE(next_retry_at, created_at)) WHERE state='pending'.
            $table->unsignedTinyInteger('pending_marker')
                ->storedAs("CASE WHEN state = 'PENDING' THEN 1 ELSE NULL END")
                ->nullable();
            $table->index(['pending_marker', 'next_retry_at', 'created_at'], 'idx_outbox_events_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
