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
        Schema::create('ai_run_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('ai_run_id');

            $table->unsignedInteger('sequence_number');
            $table->string('checkpoint_kind', 50);
            $table->string('state_ref');
            $table->char('state_hash', 64);
            $table->timestamp('recorded_at')->useCurrent();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'ai_run_id', 'sequence_number'], 'uq_ai_run_checkpoints_sequence');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_ai_run_checkpoints_brand_id_395a5dd0');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_ai_run_checkpoints_brand_1b5acedb')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'ai_run_id'], 'fk_ai_run_checkpoints_ai_run_id_0a7c098b')->references(['organisation_id', 'brand_id', 'id'])->on('ai_runs')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_run_checkpoints');
    }
};
