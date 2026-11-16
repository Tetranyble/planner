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
        Schema::create('brand_audience_locations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('audience_segment_id');

            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')
                ->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('audience_segment_id')
                ->references('id')->on('brand_audience_segments')
                ->onDelete('restrict');
            $table->foreign('country_id')
                ->references('id')->on('countries')
                ->onDelete('restrict');
            $table->foreign('state_id')
                ->references('id')->on('states')
                ->onDelete('restrict');
            $table->foreign('city_id')
                ->references('id')->on('cities')
                ->onDelete('restrict');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_audience_loc_brand_id_c4289168');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_audience_loc_brand_760a7e98')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'audience_segment_id'], 'fk_brand_audience_loc_audience_segment__de2eab5e')->references(['organisation_id', 'brand_id', 'id'])->on('brand_audience_segments')->restrictOnDelete();
            $table->foreign(['state_id', 'country_id'], 'fk_audience_state_country')->references(['id', 'country_id'])->on('states')->restrictOnDelete();
            $table->foreign(['city_id', 'state_id', 'country_id'], 'fk_audience_city_state_country')->references(['id', 'state_id', 'country_id'])->on('cities')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_audience_locations');
    }
};
