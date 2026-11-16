<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_metric_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $t->string('provider', 24);
            $t->string('account_reference', 160);
            $t->string('external_post_reference', 190);
            $t->dateTime('observed_at');
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('reach')->default(0);
            $t->unsignedBigInteger('likes')->default(0);
            $t->unsignedBigInteger('comments')->default(0);
            $t->unsignedBigInteger('shares')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->string('source_type', 24);
            $t->string('source_reference', 240);
            $t->char('submission_key', 64);
            $t->char('payload_hash', 64);
            $t->timestamps();
            $t->unique(['organisation_id', 'brand_id', 'submission_key'], 'uq_social_metric_submission');
            $t->index(['organisation_id', 'brand_id', 'observed_at', 'id'], 'idx_social_metric_history');
            $t->index(['organisation_id', 'brand_id', 'provider', 'external_post_reference'], 'idx_social_metric_post');
            $t->foreign(['organisation_id', 'brand_id'], 'fk_social_metric_brand')
                ->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_metric_snapshots');
    }
};
