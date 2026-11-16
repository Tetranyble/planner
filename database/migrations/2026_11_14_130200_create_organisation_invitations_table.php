<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->string('email', 254);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            // MySQL nullable generated column implements one non-completed invite per org/email.
            $table->unsignedTinyInteger('pending_marker')
                ->storedAs('CASE WHEN accepted_at IS NULL AND revoked_at IS NULL THEN 1 ELSE NULL END')
                ->nullable();
            $table->timestamps();

            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['organisation_id', 'email', 'pending_marker'], 'uq_org_invitation_pending_email');
            $table->index(['organisation_id', 'expires_at'], 'idx_org_invitation_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_invitations');
    }
};
