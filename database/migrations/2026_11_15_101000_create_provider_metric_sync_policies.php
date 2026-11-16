<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_metric_sync_policies', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('configured_by_user_id');
            $t->boolean('enabled')->default(false);
            $t->unsignedTinyInteger('utc_hour')->default(8);
            $t->unsignedTinyInteger('max_posts_per_day')->default(10);
            $t->date('last_queued_day')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['organisation_id', 'brand_id'], 'uq_metric_sync_policy_brand');
            $t->index(['enabled', 'utc_hour'], 'idx_metric_sync_policy_due');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_metric_sync_policy_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $t->foreign('configured_by_user_id', 'fk_metric_sync_policy_actor')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_metric_sync_policies');
    }
};
