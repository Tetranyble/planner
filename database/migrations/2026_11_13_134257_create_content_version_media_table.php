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
        Schema::create('content_version_media', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_version_id');
            $table->unsignedBigInteger('media_asset_id');

            $table->unsignedInteger('sort_order');
            $table->string('role', 30)->default(ContentMediaRole::PRIMARY->value);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('content_version_id')->references('id')->on('content_versions')
                ->onDelete('restrict');
            $table->foreign('media_asset_id')->references('id')->on('media_assets')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'content_version_id', 'sort_order'],
                'uq_content_version_media_order'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_content_version_me_brand_id_71c062ca');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_content_version_me_brand_29bcd714')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_version_id'], 'fk_content_version_me_content_version_i_ff2b709c')->references(['organisation_id', 'brand_id', 'id'])->on('content_versions')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'media_asset_id'], 'fk_content_version_me_media_asset_id_d9c9fcd0')->references(['organisation_id', 'brand_id', 'id'])->on('media_assets')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_version_media');
    }
};
