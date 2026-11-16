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
        Schema::create('brand_audience_goals', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('audience_segment_id');

            $table->string('description');
            $table->unsignedSmallInteger('priority')->default(50);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('audience_segment_id')->references('id')->on('brand_audience_segments')
                ->onDelete('restrict');

            $table->index(
                ['organisation_id', 'brand_id', 'audience_segment_id', 'priority'],
                'idx_brand_audience_goals'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_audience_goa_brand_id_f8d871de');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_audience_goa_brand_dd947068')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'audience_segment_id'], 'fk_brand_audience_goa_audience_segment__1a669747')->references(['organisation_id', 'brand_id', 'id'])->on('brand_audience_segments')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_audience_goals');
    }
};
