<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_metric_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('published_post_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->string('provider', 24);
            $table->date('sync_day');
            $table->string('state', 24)->default('PENDING');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->char('lease_owner', 48)->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('outcome_code', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'brand_id', 'published_post_id', 'sync_day'], 'uq_metric_sync_day');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_metric_sync_scoped_id');
            $table->index(['state', 'next_attempt_at', 'id'], 'idx_metric_sync_due');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_metric_sync_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'published_post_id'], 'fk_metric_sync_post')
                ->references(['organisation_id', 'brand_id', 'id'])->on('published_posts')->restrictOnDelete();
            $table->foreign('requested_by_user_id', 'fk_metric_sync_user')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('provider_metric_observations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('published_post_id');
            $table->unsignedBigInteger('provider_metric_sync_run_id');
            $table->string('provider', 24);
            $table->string('external_post_id', 190);
            $table->string('channel_reference', 160);
            // Missing counts are not zero. YouTube videos.list exposes views/likes/comments,
            // not impressions, unique reach, advertising clicks, or attributable ROI.
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->char('response_sha256', 64);
            $table->string('provenance', 36)->default('PROVIDER_API_OBSERVED');
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->unique('provider_metric_sync_run_id', 'uq_metric_observation_run');
            $table->index(['organisation_id', 'brand_id', 'observed_at', 'id'], 'idx_metric_observation_history');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_metric_observation_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'published_post_id'], 'fk_metric_observation_post')
                ->references(['organisation_id', 'brand_id', 'id'])->on('published_posts')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'provider_metric_sync_run_id'], 'fk_metric_observation_run')
                ->references(['organisation_id', 'brand_id', 'id'])->on('provider_metric_sync_runs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_metric_observations');
        Schema::dropIfExists('provider_metric_sync_runs');
    }
};
