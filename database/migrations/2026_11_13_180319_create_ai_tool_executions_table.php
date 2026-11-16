<?php

use App\Enums\Automation\AiToolExecutionStatus;
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
        Schema::create('ai_tool_executions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('ai_run_id');

            $table->string('tool_name');
            $table->string('operation_key');
            $table->char('input_hash', 64);
            $table->string('status', 20)->default(AiToolExecutionStatus::PENDING->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('result_ref')->nullable();
            $table->string('error_code')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'ai_run_id', 'operation_key'],
                'uq_ai_tool_executions_op'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_ai_tool_executions_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_ai_tool_executions_brand_089c21e2')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'ai_run_id'], 'fk_ai_tool_executions_ai_run_id_3cff182b')->references(['organisation_id', 'brand_id', 'id'])->on('ai_runs')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tool_executions');
    }
};
