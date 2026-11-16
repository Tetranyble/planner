<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Meta callbacks do not have an authenticated Planner user or tenant.
        // The app-scoped actor is matched server-side across both Meta integrations.
        Schema::create('meta_data_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('signed_request_digest', 64)->unique();
            $table->char('actor_digest', 64)->index();
            $table->longText('actor_ciphertext')->nullable();
            $table->string('confirmation_code', 64)->unique();
            $table->string('state', 24)->default('PENDING');
            $table->unsignedInteger('matched_grants')->default(0);
            $table->unsignedInteger('processed_grants')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->timestamp('accepted_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at'], 'idx_meta_deletion_due');
        });
        Schema::create('meta_data_deletion_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('meta_data_deletion_requests')->restrictOnDelete();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('provider_connection_id');
            $table->string('state', 24)->default('PENDING');
            $table->string('review_reason', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'provider_connection_id'], 'uq_meta_deletion_target_grant');
            $table->foreign(['organisation_id', 'provider_connection_id'], 'fk_meta_deletion_target_grant')
                ->references(['organisation_id', 'id'])->on('provider_connections')->restrictOnDelete();
            $table->index(['state', 'request_id'], 'idx_meta_target_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_data_deletion_targets');
        Schema::dropIfExists('meta_data_deletion_requests');
    }
};
