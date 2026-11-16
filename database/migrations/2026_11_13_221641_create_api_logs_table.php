<?php

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
        /**
         * renamed from api_call_log to api_logs
         **/
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->string('provider', 50);
            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->string('operation', 100);
            $table->string('correlation_id');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('rate_limit_class', 30)->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'provider', 'occurred_at'], 'idx_api_logs_provider');
            $table->foreign(['organisation_id', 'social_account_id'], 'fk_api_logs_social_account_id_5ad25c8a')->references(['organisation_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
