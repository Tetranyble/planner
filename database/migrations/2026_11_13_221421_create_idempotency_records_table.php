<?php

use App\Enums\Operation\IdempotencyRecordState;
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
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->string('client_key');
            $table->string('operation');
            $table->string('idempotency_key');
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->string('state', 20)->default(IdempotencyRecordState::PROCESSING->value);
            $table->timestamp('expires_at')->index();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'client_key', 'operation', 'idempotency_key'], 'uq_idempotency_records_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
