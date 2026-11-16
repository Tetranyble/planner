<?php

use App\Enums\Connection\SocialAccountStatus;
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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('provider_connection_id');

            $table->string('provider');
            $table->string('external_account_id');
            $table->string('account_kind');
            $table->string('display_name');
            $table->string('handle')->nullable();

            $table->string('status', 20)->default(SocialAccountStatus::ACTIVE->value);
            $table->timestampTz('last_sync_at')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('provider_connection_id')->references('id')->on('provider_connections')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'provider', 'external_account_id'], 'uq_social_accounts_provider_account');

            $table->index(['organisation_id', 'brand_id', 'status'], 'idx_social_accounts_brand'
            );
            $table->unique(['organisation_id', 'id'], 'uq_social_accounts_organisation_id');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_social_accounts_brand_id_2c50bcc4');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_social_accounts_brand_ec096bf0')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'provider_connection_id'], 'fk_social_accounts_provider_connecti_ce67d8d2')->references(['organisation_id', 'id'])->on('provider_connections')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
