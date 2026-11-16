<?php

use App\Enums\Analytic\AnalyticsPeriodGrain;
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
        Schema::create('analytic_rollups', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('social_account_id')->nullable();

            $table->string('period_grain', 10)->default(AnalyticsPeriodGrain::DAY->value);
            $table->date('period_start');
            $table->string('metric_key');
            $table->decimal('metric_value', 20, 4);
            $table->timestamp('computed_at')->useCurrent();

            // Same NULLS NOT DISTINCT emulation as analytics_snapshots.
            $table->string('unique_key', 64)
                ->storedAs("SHA2(CONCAT_WS('|',
                    organisation_id,
                    brand_id,
                    COALESCE(campaign_id, 0),
                    COALESCE(social_account_id, 0),
                    period_grain,
                    DATE_FORMAT(period_start, '%Y-%m-%d'),
                    metric_key
                ), 256)")
                ->nullable();
            $table->unique('unique_key', 'uq_analytics_rollups_unique_key');

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('campaign_id')->references('id')->on('campaigns')
                ->onDelete('restrict');

            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_analytics_rollups_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_analytic_rollups_brand_0dc7445c')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'campaign_id'], 'fk_analytic_rollups_campaign_id_1285a116')->references(['organisation_id', 'brand_id', 'id'])->on('campaigns')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_analytic_rollups_social_account_id_5e18f01c')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytic_rollups');
    }
};
