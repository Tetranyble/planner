<?php

use App\Enums\Publishing\RecurringScheduleState;
use App\Enums\TimeZone;
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
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_target_id');

            $table->string('rrule');
            $table->string('timezone', 64)->default(TimeZone::AFRICA_LAGOS->value);
            // start_local is wall-clock in the schedule's timezone, not a UTC instant.
            // Use DATETIME (not TIMESTAMP) so MySQL does not apply timezone conversion.
            $table->dateTime('start_local');
            $table->timestamp('end_at')->nullable();
            $table->timestamp('next_expand_at')->nullable();
            $table->string('state', 20)->default(RecurringScheduleState::ACTIVE->value);
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('content_target_id')->references('id')->on('content_targets')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_recurring_schedule_brand_id_a7e13ddc');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_recurring_schedule_brand_e4d4e013')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_target_id'], 'fk_recurring_schedule_content_target_id_7716b211')->references(['organisation_id', 'brand_id', 'id'])->on('content_targets')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_schedules');
    }
};
