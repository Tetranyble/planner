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
        Schema::create('campaign_goals', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id');

            $table->string('metric_key');
            $table->decimal('target_value', 18, 4);
            $table->timestamp('due_at')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('campaign_id')->references('id')->on('campaigns')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'campaign_id', 'metric_key'],
                'uq_campaign_goals_metric'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_campaign_goals_brand_id_557699f0');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_campaign_goals_brand_93edee3c')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'campaign_id'], 'fk_campaign_goals_campaign_id_18e4f3db')->references(['organisation_id', 'brand_id', 'id'])->on('campaigns')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_goals');
    }
};
