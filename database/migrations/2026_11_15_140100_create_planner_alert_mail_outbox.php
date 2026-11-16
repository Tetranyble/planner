<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_alert_mail_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('alert_event_id');
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('state', 16)->default('PENDING');
            $table->uuid('lease_owner')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['alert_event_id', 'recipient_user_id'], 'uq_planner_alert_delivery_idempotent');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_planner_alert_delivery_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'alert_event_id'], 'fk_planner_alert_delivery_evidence')
                ->references(['organisation_id', 'brand_id', 'id'])->on('planner_operational_alert_events')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_alert_mail_outbox');
    }
};
