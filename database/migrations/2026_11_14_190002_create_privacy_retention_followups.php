<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_retention_followups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->foreignId('review_decision_id')->unique('uq_privacy_retention_decision')
                ->constrained('meta_data_deletion_review_decisions')->restrictOnDelete();
            $table->timestamp('due_at');
            $table->string('status', 20)->default('DUE');
            $table->unsignedBigInteger('acknowledged_by_user_id')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgement_notes')->nullable();
            $table->timestamps();
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
            $table->foreign('acknowledged_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['organisation_id', 'status', 'due_at'], 'idx_privacy_retention_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_retention_followups');
    }
};
