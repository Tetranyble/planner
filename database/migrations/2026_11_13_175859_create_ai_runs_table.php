<?php

use App\Enums\Automation\AiRunPurpose;
use App\Enums\Automation\AiRunStatus;
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
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('automation_run_id')->nullable();

            $table->string('purpose', 30)->default(AiRunPurpose::RESEARCH->value);
            $table->string('status', 20)->default(AiRunStatus::QUEUED->value);
            $table->string('model_provider', 50);
            $table->string('model_name');
            $table->string('input_ref')->nullable();
            $table->string('output_ref')->nullable();
            $table->string('lease_owner')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error_code')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign(['organisation_id', 'brand_id'])->references(['organisation_id', 'id'])->on('brands')
                ->onDelete('restrict');
            $table->foreign('automation_run_id')->references('id')->on('automation_runs')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_ai_runs_brand_id');
            $table->index(['status', 'lease_until'], 'idx_ai_runs_lease');
            $table->foreign(['organisation_id', 'brand_id', 'automation_run_id'], 'fk_ai_runs_automation_run_id_15458b50')->references(['organisation_id', 'brand_id', 'id'])->on('automation_runs')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
