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
        Schema::create('social_account_status_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('social_account_id');

            $table->string('event_type');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->timestampTz('recorded_at')->useCurrent();
            $table->json('details')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_social_account_sta_brand_id_63f6be76');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_social_account_sta_brand_6d90e94e')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_social_account_sta_social_account_id_837968a5')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_account_status_events');
    }
};
