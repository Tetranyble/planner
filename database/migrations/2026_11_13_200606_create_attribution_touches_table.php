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
        Schema::create('attribution_touches', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('tracking_link_id');

            $table->string('visitor_key');
            $table->string('touch_kind', 30);
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('referrer_domain')->nullable();
            $table->json('context');

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('tracking_link_id')->references('id')->on('tracking_links')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'brand_id', 'visitor_key', 'occurred_at'], 'idx_attribution_visitor');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_attribution_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_attribution_touche_brand_f257a811')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'tracking_link_id'], 'fk_attribution_touche_tracking_link_id_f5783b49')->references(['organisation_id', 'brand_id', 'id'])->on('tracking_links')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribution_touches');
    }
};
