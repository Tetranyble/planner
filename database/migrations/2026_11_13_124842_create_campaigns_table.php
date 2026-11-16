<?php

use App\Enums\Content\CampaignObjective;
use App\Enums\Content\CampaignStatus;
use App\Enums\Currency;
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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->string('name');
            $table->string('objective', 30)->default(CampaignObjective::AWARENESS->value);
            $table->string('status', 20)->default(CampaignStatus::DRAFT->value);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->bigInteger('budget_minor')->default(0);
            $table->char('currency', 3)->default(Currency::NGN->value);
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->text('brief')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('owner_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            // Required by child FKs (campaign_goals, content_items).
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_campaigns_tenant_brand_id');

            $table->index(
                ['organisation_id', 'brand_id', 'status', 'starts_at'],
                'idx_campaigns_active'
            );
            $table->foreign(['organisation_id', 'brand_id'], 'fk_campaigns_brand_2239587a')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
