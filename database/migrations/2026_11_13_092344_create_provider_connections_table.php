<?php

use App\Enums\Connection\ProviderConnectionStatus;
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
        Schema::create('provider_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->string('provider');

            $table->string('external_actor_id')->nullable();
            $table->text('access_token_secret_ref');
            $table->text('refresh_token_secret_ref')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->string('status', 30)->default(ProviderConnectionStatus::CONNECTED->value);
            $table->timestampTz('last_verified_at')->nullable();
            $table->text('reconnect_reason')->nullable();

            // FK to organisations (uuid) — adjust column type if organisations uses bigint.
            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'provider', 'status'], 'idx_provider_connections_status');
            $table->unique(['organisation_id', 'id'], 'uq_provider_connectio_organisation_id_39a844d7');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_connections');
    }
};
