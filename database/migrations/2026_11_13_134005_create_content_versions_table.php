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
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_item_id');

            $table->unsignedInteger('version_number');
            $table->text('copy_text')->nullable();
            $table->json('body_json');
            $table->char('content_hash', 64);
            $table->unsignedBigInteger('source_ai_run_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('content_item_id')->references('id')->on('content_items')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'content_item_id', 'version_number'],
                'uq_content_versions_number'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_content_versions_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_content_versions_brand_303ace01')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_item_id'], 'fk_content_versions_content_item_id_c9256392')->references(['organisation_id', 'brand_id', 'id'])->on('content_items')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
