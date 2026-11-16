<?php

use App\Enums\Content\MediaVariantState;
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
        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('media_asset_id');

            $table->string('variant_key');
            $table->string('object_key');
            $table->string('mime_type');
            $table->bigInteger('byte_size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->bigInteger('duration_ms')->nullable();
            $table->string('state', 20)->default(MediaVariantState::PROCESSING->value);
            $table->string('error_code')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('media_asset_id')->references('id')->on('media_assets')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'media_asset_id', 'variant_key'],
                'uq_media_variants_key'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_media_variants_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_media_variants_brand_08d19de9')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'media_asset_id'], 'fk_media_variants_media_asset_id_20bcfd8e')->references(['organisation_id', 'brand_id', 'id'])->on('media_assets')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
