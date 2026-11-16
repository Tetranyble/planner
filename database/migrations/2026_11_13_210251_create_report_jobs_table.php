<?php

use App\Enums\Analytic\ReportFormat;
use App\Enums\Analytic\ReportJobState;
use App\Enums\Analytic\ReportType;
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
        Schema::create('report_jobs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id')->nullable();

            $table->string('report_type', 20)->default(ReportType::CAMPAIGN->value);
            $table->string('format', 10)->default(ReportFormat::CSV->value);
            $table->string('state', 20)->default(ReportJobState::QUEUED->value);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('output_object_key')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error_code')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('campaign_id')->references('id')->on('campaigns')
                ->onDelete('restrict');

            $table->foreign('requested_by_user_id')->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_report_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_report_jobs_brand_43d4ec7d')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'campaign_id'], 'fk_report_jobs_campaign_id_a68afb69')->references(['organisation_id', 'brand_id', 'id'])->on('campaigns')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};
