<?php

use App\Enums\Automation\AiToolAttemptOutcome;
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
        Schema::create('ai_tool_attempts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('ai_tool_execution_id');

            $table->unsignedInteger('attempt_number');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->string('outcome', 20)->default(AiToolAttemptOutcome::STARTED->value);
            $table->string('error_code')->nullable();
            $table->string('response_ref')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('ai_tool_execution_id')->references('id')->on('ai_tool_executions')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'ai_tool_execution_id', 'attempt_number'], 'uq_ai_tool_attempts_number');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_ai_tool_attempts_brand_id_4ba1f6ae');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_ai_tool_attempts_brand_02430919')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'ai_tool_execution_id'], 'fk_ai_tool_attempts_ai_tool_execution_163888bc')->references(['organisation_id', 'brand_id', 'id'])->on('ai_tool_executions')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tool_attempts');
    }
};
