<?php

use App\Enums\Publishing\PublishedPostRemoteStatus;
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
        Schema::create('published_posts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('publication_job_id');
            $table->unsignedBigInteger('social_account_id');

            $table->string('provider', 50);
            $table->string('external_post_id');
            $table->text('external_url')->nullable();
            $table->timestamp('published_at');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('remote_status', 20)->default(PublishedPostRemoteStatus::PUBLISHED->value);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('publication_job_id')->references('id')->on('publication_jobs')
                ->onDelete('restrict');

            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'publication_job_id'], 'uq_published_posts_job');

            $table->unique(['organisation_id', 'social_account_id', 'external_post_id'], 'uq_published_posts_external');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_published_posts_brand_id_c635283a');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_published_posts_brand_054d1d72')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'publication_job_id'], 'fk_published_posts_publication_job_i_cb0cd08f')->references(['organisation_id', 'brand_id', 'id'])->on('publication_jobs')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_published_posts_social_account_id_e614208b')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('published_posts');
    }
};
