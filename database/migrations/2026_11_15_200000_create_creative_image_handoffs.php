<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_image_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->char('sha256', 64);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('mime_type', 32)->default('image/png');
            // Only metadata is persisted; provider image bytes never enter this table.
            $table->string('state', 16)->default('AWAITING_UPLOAD');
            $table->unsignedBigInteger('storage_media_id')->nullable();
            $table->unsignedBigInteger('media_asset_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'brand_id', 'actor_user_id', 'state', 'expires_at'], 'creative_handoff_scope_status');
            $table->index(['state', 'expires_at']);
            $table->foreign(['organisation_id', 'brand_id'], 'fk_creative_handoffs_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'media_asset_id'], 'fk_creative_handoffs_asset')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_assets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_image_handoffs');
    }
};
