<?php

use App\Enums\Growth\TrackingLinkStatus;
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
        Schema::create('tracking_links', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id')->nullable();

            $table->string('short_code', 32)->unique();
            $table->text('destination_url');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('status', 20)->default(TrackingLinkStatus::ACTIVE->value);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('campaign_id')->references('id')->on('campaigns')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_tracking_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_tracking_links_brand_be0dde0a')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'campaign_id'], 'fk_tracking_links_campaign_id_8d0c3b66')->references(['organisation_id', 'brand_id', 'id'])->on('campaigns')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_links');
    }
};
