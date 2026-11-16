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
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('lead_contact_id')->nullable();

            $table->string('activity_type', 50);
            $table->timestamp('occurred_at')->useCurrent();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('summary');
            $table->json('metadata');

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('lead_id')->references('id')->on('leads')
                ->onDelete('restrict');

            $table->foreign('lead_contact_id')->references('id')->on('lead_contacts')
                ->onDelete('restrict');

            $table->foreign('actor_user_id')->references('id')->on('users')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'lead_id', 'occurred_at'], 'idx_lead_activities_timeline');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_lead_activities_brand_id_a329fe9d');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_lead_activities_brand_fb2f188a')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_id'], 'fk_lead_activities_lead_id_6456d783')->references(['organisation_id', 'brand_id', 'id'])->on('leads')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_contact_id'], 'fk_lead_activities_lead_contact_id_48c1e8d2')->references(['organisation_id', 'brand_id', 'id'])->on('lead_contacts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
