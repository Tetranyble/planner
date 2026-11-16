<?php

use App\Enums\Growth\LeadSourceKind;
use App\Enums\Growth\LeadStatus;
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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id')->nullable();

            $table->string('organisation_name');
            $table->string('industry')->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('country_id');
            $table->string('source_kind', 20)->default(LeadSourceKind::ORGANIC->value);
            $table->string('source_ref')->nullable();
            $table->string('status', 20)->default(LeadStatus::NEW->value);
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->text('notes')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('campaign_id')->references('id')->on('campaigns')
                ->onDelete('restrict');
            $table->foreign('country_id')->references('id')->on('countries')
                ->onDelete('restrict');

            $table->foreign('owner_user_id')->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_leads_tenant_brand_id');
            $table->index(['organisation_id', 'brand_id', 'status', 'created_at'],
                'idx_leads_pipeline'
            );
            $table->foreign(['organisation_id', 'brand_id'], 'fk_leads_brand_0adbf19a')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'campaign_id'], 'fk_leads_campaign_id_12932579')->references(['organisation_id', 'brand_id', 'id'])->on('campaigns')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
