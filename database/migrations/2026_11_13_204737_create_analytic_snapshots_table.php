<?php

use App\Enums\Analytic\AnalyticsCollectionStatus;
use App\Enums\Analytic\AnalyticsMetricScope;
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
        Schema::create('analytic_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('social_account_id');
            $table->unsignedBigInteger('published_post_id')->nullable();

            $table->string('metric_scope', 20)->default(AnalyticsMetricScope::ACCOUNT->value);
            $table->timestamp('observed_at');
            $table->timestamp('provider_as_of')->nullable();
            $table->json('metrics');
            $table->string('collection_status', 20)
                ->default(AnalyticsCollectionStatus::COMPLETE->value);

            // MySQL has no UNIQUE NULLS NOT DISTINCT.
            // Hash the normalized tuple (NULL → '' for published_post_id) into a
            // STORED generated column, then put a plain unique index on it.
            $table->string('unique_key', 64)
                ->storedAs("SHA2(CONCAT_WS('|',
                    organisation_id,
                    social_account_id,
                    COALESCE(published_post_id, 0),
                    DATE_FORMAT(observed_at, '%Y-%m-%d %H:%i:%s')
                ), 256)")
                ->nullable();
            $table->unique('unique_key', 'uq_analytics_snapshots_unique_key');

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');
            $table->foreign('published_post_id')->references('id')->on('published_posts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_analytics_snap_brand_id');
            $table->index(['organisation_id', 'brand_id', 'observed_at'], 'idx_analytics_snap_recent');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_analytic_snapshots_brand_48f99a97')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_analytic_snapshots_social_account_id_c038bb7c')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'published_post_id'], 'fk_analytic_snapshots_published_post_id_8df33bd8')->references(['organisation_id', 'brand_id', 'id'])->on('published_posts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytic_snapshots');
    }
};
