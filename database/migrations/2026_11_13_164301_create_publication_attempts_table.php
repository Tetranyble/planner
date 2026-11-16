<?php

use App\Enums\Publishing\PublicationAttemptResult;
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
        Schema::create('publication_attempts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('publication_job_id');

            $table->unsignedInteger('attempt_number');
            $table->string('provider_request_id')->nullable();
            $table->string('provider_upload_id')->nullable();
            $table->string('operation_key');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->string('result', 20)->default(PublicationAttemptResult::STARTED->value);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code')->nullable();
            $table->json('response_redacted');

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('publication_job_id')->references('id')->on('publication_jobs')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'publication_job_id', 'attempt_number'],
                'uq_publication_attempts_number'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_publication_attemp_brand_id_b1833608');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_publication_attemp_brand_27ba86c0')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_publication_attemp_publication_job_i_fa5e2aec')->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_attempts');
    }
};
