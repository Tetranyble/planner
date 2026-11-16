<?php

use App\Enums\Operation\WebhookEventState;
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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->string('provider', 50);
            $table->string('external_event_id');
            $table->unsignedBigInteger('social_account_id')->nullable();

            $table->timestamp('received_at')->useCurrent();
            $table->boolean('verified')->default(false);
            $table->string('state', 20)->default(WebhookEventState::PENDING->value);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->json('redacted_payload');
            $table->string('error_code')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'provider', 'external_event_id'], 'uq_webhook_events_external');
            $table->foreign(['organisation_id', 'social_account_id'], 'fk_webhook_events_social_account_id_309e22d1')->references(['organisation_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
