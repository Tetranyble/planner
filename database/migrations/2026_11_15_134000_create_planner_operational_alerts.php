<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_operational_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->string('signal', 60);
            $table->string('state', 16)->default('OPEN');
            $table->unsignedBigInteger('observed_count');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'brand_id', 'signal'], 'uq_planner_alert_scope_signal');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_planner_alert_scope_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_planner_alert_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
        });
        Schema::create('planner_operational_alert_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('planner_operational_alert_id');
            $table->string('event_type', 24);
            $table->unsignedBigInteger('observed_count');
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->foreign(['organisation_id', 'brand_id', 'planner_operational_alert_id'], 'fk_planner_alert_event_scope')
                ->references(['organisation_id', 'brand_id', 'id'])->on('planner_operational_alerts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_operational_alert_events');
        Schema::dropIfExists('planner_operational_alerts');
    }
};
