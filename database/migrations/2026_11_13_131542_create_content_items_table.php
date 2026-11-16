<?php

use App\Enums\Content\ContentItemState;
use App\Enums\ContentKind;
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
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('content_series_id')->nullable();

            $table->string('title');
            $table->string('content_kind', 20)->default(ContentKind::ARTICLE->value);
            $table->string('state', 20)->default(ContentItemState::DRAFT->value);
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('campaign_id')->references('id')->on('campaigns')
                ->onDelete('restrict');
            $table->foreign('content_series_id')->references('id')->on('content_series')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_content_items_brand_id');
            $table->index(
                ['organisation_id', 'brand_id', 'campaign_id', 'created_at'],
                'idx_content_items_campaign'
            );
            $table->foreign(['organisation_id', 'brand_id'], 'fk_content_items_brand_dfa29137')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'campaign_id'], 'fk_content_items_campaign_id_b35648c9')->references(['organisation_id', 'brand_id', 'id'])->on('campaigns')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_series_id'], 'fk_content_items_content_series_id_43484e8f')->references(['organisation_id', 'brand_id', 'id'])->on('content_series')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
