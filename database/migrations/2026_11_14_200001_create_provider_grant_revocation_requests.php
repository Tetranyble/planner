<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_grant_revocation_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->foreignId('grant_group_id')->constrained('provider_grant_groups')->restrictOnDelete();
            $table->unsignedBigInteger('provider_connection_id');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('provider', 24);
            $table->string('status', 32); // PREPARED, READY, IN_FLIGHT, ACKNOWLEDGED, MANUAL_REQUIRED, UNCERTAIN, CANCELLED
            $table->unsignedInteger('affected_connections');
            $table->char('impact_digest', 64);
            $table->string('reason', 250);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('provider_acknowledged_at')->nullable();
            $table->string('safe_error_code', 70)->nullable();
            $table->timestamps();
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
            $table->foreign(['organisation_id', 'provider_connection_id'], 'fk_revocation_provider_connection')
                ->references(['organisation_id', 'id'])->on('provider_connections')->restrictOnDelete();
            $table->index(['status', 'lease_until', 'id'], 'idx_grant_revoke_due');
            $table->index(['grant_group_id', 'status'], 'idx_grant_revoke_active');
            $table->index(['organisation_id', 'created_at'], 'idx_grant_revoke_org');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_grant_revocation_requests');
    }
};
