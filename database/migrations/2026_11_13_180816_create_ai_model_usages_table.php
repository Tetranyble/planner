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
        Schema::create('ai_model_usages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('ai_run_id');

            $table->string('invocation_key');
            $table->string('model_provider', 50);
            $table->string('model_name');
            $table->bigInteger('input_tokens')->default(0);
            $table->bigInteger('output_tokens')->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamp('recorded_at')->useCurrent();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'ai_run_id', 'invocation_key'], 'uq_ai_model_usage_invocation');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_ai_model_usages_brand_id_13978e42');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_ai_model_usages_brand_d337489d')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'ai_run_id'], 'fk_ai_model_usages_ai_run_id_14573630')->references(['organisation_id', 'brand_id', 'id'])->on('ai_runs')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model_usages');
    }
};
