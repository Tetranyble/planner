<?php

use App\Enums\Content\ContentMediaRole;
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
        Schema::create('content_target_media', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_target_id');
            $table->unsignedBigInteger('media_variant_id');

            $table->unsignedInteger('sort_order');
            $table->string('role', 30)->default(ContentMediaRole::PRIMARY->value);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('content_target_id')->references('id')->on('content_targets')
                ->onDelete('restrict');
            $table->foreign('media_variant_id')->references('id')->on('media_variants')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'content_target_id', 'sort_order'],
                'uq_content_target_media_order'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_content_target_med_brand_id_82f31a87');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_content_target_med_brand_e3d72140')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_target_id'], 'fk_content_target_med_content_target_id_d3821f97')->references(['organisation_id', 'brand_id', 'id'])->on('content_targets')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'media_variant_id'], 'fk_content_target_med_media_variant_id_6336ceee')->references(['organisation_id', 'brand_id', 'id'])->on('media_variants')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_target_media');
    }
};
