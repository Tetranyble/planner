<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_resumable_transfers', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('media_publication_run_id');
            $t->unsignedBigInteger('media_asset_id');
            $t->unsignedBigInteger('armed_by_user_id');
            $t->string('state', 32)->default('HELD');
            $t->string('next_action', 20)->default('INIT');
            $t->unsignedBigInteger('total_bytes');
            $t->unsignedBigInteger('committed_bytes')->default(0);
            $t->char('canonical_sha256', 64);
            $t->string('canonical_mime', 100);
            $t->text('session_url_ciphertext')->nullable(); // Laravel encrypted cast
            $t->text('video_id_ciphertext')->nullable(); // Laravel encrypted cast
            $t->unsignedInteger('poll_count')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('sequence')->default(0);
            $t->string('lease_owner', 80)->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->timestamp('next_check_at')->nullable();
            $t->string('blocker_code', 70)->nullable();
            $t->unique(['organisation_id', 'brand_id', 'id'], 'uq_yt_transfer_scope');
            $t->unique(['organisation_id', 'media_publication_run_id'], 'uq_yt_transfer_media_run');
            $t->index(['state', 'next_check_at', 'id'], 'idx_yt_transfer_due');
            $t->foreign(['organisation_id', 'brand_id', 'media_publication_run_id'], 'fk_yt_transfer_run')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_publication_runs')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'media_asset_id'], 'fk_yt_transfer_asset')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_assets')->restrictOnDelete();
            $t->foreign('armed_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('youtube_resumable_attempts', function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('youtube_resumable_transfer_id');
            $t->unsignedInteger('sequence');
            $t->string('action', 20);
            $t->unsignedBigInteger('offset_bytes');
            $t->string('state', 25)->default('STARTED');
            $t->string('outcome_code', 70)->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unique(['youtube_resumable_transfer_id', 'sequence'], 'uq_yt_transfer_attempt_sequence');
            $t->foreign(['organisation_id', 'brand_id', 'youtube_resumable_transfer_id'], 'fk_yt_attempt_transfer')
                ->references(['organisation_id', 'brand_id', 'id'])->on('youtube_resumable_transfers')->restrictOnDelete();
        });
        Schema::create('youtube_resumable_events', function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('youtube_resumable_transfer_id');
            $t->string('action', 50);
            $t->string('from_state', 32)->nullable();
            $t->string('to_state', 32);
            $t->json('safe_details')->nullable();
            $t->foreign(['organisation_id', 'brand_id', 'youtube_resumable_transfer_id'], 'fk_yt_event_transfer')
                ->references(['organisation_id', 'brand_id', 'id'])->on('youtube_resumable_transfers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_resumable_events');
        Schema::dropIfExists('youtube_resumable_attempts');
        Schema::dropIfExists('youtube_resumable_transfers');
    }
};
