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
        Schema::create('api_rate_limit_buckets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->string('provider', 50);
            $table->string('bucket_key');
            $table->integer('limit_count')->nullable();
            $table->integer('remaining_count')->nullable();
            $table->timestamp('reset_at')->nullable();
            $table->timestamp('last_observed_at')->useCurrent();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'provider', 'bucket_key'], 'uq_rate_limit_buckets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_rate_limit_buckets');
    }
};
