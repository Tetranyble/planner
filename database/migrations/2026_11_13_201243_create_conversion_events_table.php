<?php

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
        Schema::create('conversion_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('tracking_link_id')->nullable();

            $table->string('source_system', 50);
            $table->string('source_event_id');
            $table->string('event_type', 50);
            $table->bigInteger('value_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->timestamp('occurred_at');
            $table->timestamp('received_at')->useCurrent();
            $table->json('properties');

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('campaign_id')->references('id')->on('campaigns')
                ->onDelete('restrict');

            $table->foreign('lead_id')->references('id')->on('leads')
                ->onDelete('restrict');
            $table->foreign('tracking_link_id')->references('id')->on('tracking_links')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'source_system', 'source_event_id'],
                'uq_conversion_esource'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_conversion_ebrand_id');
            $table->index(['organisation_id', 'brand_id', 'event_type', 'occurred_at'], 'idx_conversion_eevent');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_conversion_events_brand_781aed01')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'campaign_id'], 'fk_conversion_events_campaign_id_59a68c27')->references(['organisation_id', 'brand_id', 'id'])->on('campaigns')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_id'], 'fk_conversion_events_lead_id_71f613a5')->references(['organisation_id', 'brand_id', 'id'])->on('leads')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'tracking_link_id'], 'fk_conversion_events_tracking_link_id_ce3b4e24')->references(['organisation_id', 'brand_id', 'id'])->on('tracking_links')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_events');
    }
};
