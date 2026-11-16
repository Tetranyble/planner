<?php

use App\Enums\DecisionRole;
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
        Schema::create('brand_audience_roles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('audience_segment_id');

            $table->string('role');
            $table->string('decision_role', 30)->default(DecisionRole::DECISION_MAKER->value); // DecisionRole

            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('restrict');

            $table->foreign('audience_segment_id')
                ->references('id')
                ->on('brand_audience_segments')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'brand_id', 'audience_segment_id', 'role', 'decision_role'],
                'uq_brand_audience_roles');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_audience_rol_brand_id_667102da');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_audience_rol_brand_128e6ac6')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'audience_segment_id'], 'fk_brand_audience_rol_audience_segment__c5cc482a')->references(['organisation_id', 'brand_id', 'id'])->on('brand_audience_segments')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_audience_roles');
    }
};
