<?php

use App\Enums\Content\MediaAssetState;
use App\Enums\Content\MediaSourceType;
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
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            // Canonical physical media is owned by tetranyble/storage.
            // Backfill/validate this link in the storage integration service.
            $table->unsignedBigInteger('storage_media_id')->nullable()->index('idx_marketing_storage_media');
            $table->string('object_key')->nullable(); // Legacy snapshot, not the source of truth
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('byte_size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('asset_state', 20)->default(MediaAssetState::UPLOADED->value);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->bigInteger('duration_ms')->nullable();
            $table->string('source_type', 20)->default(MediaSourceType::UPLOAD->value);

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_ai_run_id')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_media_assets_brand_id');
            $table->index(['organisation_id', 'brand_id', 'sha256'], 'idx_media_assets_digest');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_media_assets_brand_0e0f60c1')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
