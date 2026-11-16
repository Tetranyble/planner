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
        Schema::create('content_item_labels', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_item_id');
            $table->unsignedBigInteger('content_label_id');

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('content_item_id')->references('id')->on('content_items')
                ->onDelete('restrict');
            $table->foreign('content_label_id')->references('id')->on('content_labels')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'content_item_id', 'content_label_id'],
                'uq_content_item_labels_pair'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_content_item_label_brand_id_dcd88cb8');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_content_item_label_brand_a676a3df')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_item_id'], 'fk_content_item_label_content_item_id_d7fb2e3f')->references(['organisation_id', 'brand_id', 'id'])->on('content_items')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_label_id'], 'fk_content_item_label_content_label_id_09c9ea8b')->references(['organisation_id', 'brand_id', 'id'])->on('content_labels')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_item_labels');
    }
};
