<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_oauth_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 24);
            $table->char('state_digest', 64)->unique();
            $table->string('redirect_uri', 512);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->foreign(['organisation_id', 'brand_id'], 'fk_social_oauth_attempts_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->index(['organisation_id', 'brand_id', 'user_id', 'expires_at'], 'idx_social_oauth_attempts_expiry');
        });
        // Provider identities must be unique for an organisation, including concurrent OAuth callbacks.
        // Preflight existing rows for duplicates before deploying this additive index.
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'provider', 'external_actor_id'], 'uq_provider_connection_actor');
        });
        Schema::create('provider_connection_credentials', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('provider_connection_id');
            // Encrypted by Laravel Crypt using APP_KEY, never serialised as API resources.
            $table->longText('access_token_ciphertext');
            $table->longText('refresh_token_ciphertext')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'provider_connection_id'], 'uq_provider_credential_connection');
            $table->foreign(['organisation_id', 'provider_connection_id'], 'fk_provider_credential_connection')
                ->references(['organisation_id', 'id'])->on('provider_connections')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_connection_credentials');
        Schema::dropIfExists('social_oauth_attempts');
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->dropUnique('uq_provider_connection_actor');
        });
    }
};
