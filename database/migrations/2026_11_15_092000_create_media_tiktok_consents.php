<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_tiktok_consents', function (Blueprint $t): void {
            $t->id();
            $t->timestamps();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedBigInteger('brand_id');
            $t->unsignedBigInteger('media_publication_run_id');
            $t->unsignedBigInteger('consenting_user_id');
            $t->char('approval_payload_hash', 64);
            $t->char('media_sha256', 64);
            $t->char('grant_fingerprint', 64);
            $t->json('privacy_selection');
            $t->json('verified_creator_options');
            $t->timestamp('verified_at');
            $t->unique(['organisation_id', 'media_publication_run_id'], 'uq_tiktok_consent_run');
            $t->foreign(['organisation_id', 'brand_id', 'media_publication_run_id'], 'fk_tiktok_consent_run')
                ->references(['organisation_id', 'brand_id', 'id'])->on('media_publication_runs')->restrictOnDelete();
            $t->foreign('consenting_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_tiktok_consents');
    }
};
