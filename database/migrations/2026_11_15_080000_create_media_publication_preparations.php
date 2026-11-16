<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_publication_preparations', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('publication_job_id');
            $t->unsignedBigInteger('media_asset_id');
            $t->unsignedBigInteger('prepared_by_user_id');
            $t->string('provider', 20);
            $t->string('protocol', 40);
            $t->string('state', 32)->default('PREPARED');
            $t->char('media_sha256', 64);
            $t->char('approval_payload_hash', 64);
            $t->json('protocol_manifest');
            $t->unsignedBigInteger('version')->default(1);
            $t->string('blocker_code', 70)->nullable();
            $t->unique(['organisation_id', 'publication_job_id'], 'uq_media_publication_job');
            $t->unique(['organisation_id', 'brand_id', 'id'], 'uq_media_preparation_tenant');
            $t->index(['organisation_id', 'brand_id', 'state'], 'idx_media_preparation_scope_state');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_media_preparation_brand')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_media_preparation_job')->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();
            $t->foreign(['organisation_id', 'brand_id', 'media_asset_id'], 'fk_media_preparation_asset')->references(['organisation_id', 'brand_id', 'id'])->on('media_assets')->restrictOnDelete();
            $t->foreign('prepared_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('media_publication_preparation_events', function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('media_publication_preparation_id');
            $t->string('action', 40);
            $t->string('from_state', 32)->nullable();
            $t->string('to_state', 32);
            $t->unsignedBigInteger('actor_user_id')->nullable();
            $t->json('details')->nullable();
            $t->index(['organisation_id', 'brand_id', 'media_publication_preparation_id'], 'idx_media_prep_events_scope');
            $t->foreign(['organisation_id', 'brand_id', 'media_publication_preparation_id'], 'fk_media_prep_event_parent')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_publication_preparations')->restrictOnDelete();
            $t->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publication_preparation_events');
        Schema::dropIfExists('media_publication_preparations');
    }
};
