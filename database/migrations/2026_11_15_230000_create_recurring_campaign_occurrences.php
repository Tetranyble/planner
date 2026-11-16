<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_campaign_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('recurring_schedule_id')->constrained('recurring_schedules')->restrictOnDelete();
            $table->uuid('occurrence_key');
            $table->dateTime('local_at'); // Wall-clock input, not a TIMESTAMP conversion.
            $table->timestamp('run_at'); // UTC instant, derived by the same source calculator.
            $table->timestamp('held_at')->nullable(); // Only set by an explicit operator hold.
            $table->timestamps();
            $table->unique(['recurring_schedule_id', 'occurrence_key'], 'uq_recurring_occurrence_key');
            $table->index(['organisation_id', 'brand_id', 'run_at'], 'ix_recurring_brand_occurrences');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_recurring_occurrence_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'recurring_schedule_id'], 'fk_recurring_occurrence_schedule')
                ->references(['organisation_id', 'brand_id', 'id'])->on('recurring_schedules')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_campaign_occurrences');
    }
};
