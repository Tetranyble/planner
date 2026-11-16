<?php

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
        Schema::create('automation_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('automation_rule_id');

            $table->unsignedInteger('version_number');
            $table->json('trigger_config');
            $table->json('condition_config');
            $table->json('action_plan');
            $table->json('policy_snapshot');
            $table->char('config_hash', 64);
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('automation_rule_id')->references('id')->on('automation_rules')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'automation_rule_id', 'version_number'],
                'uq_automation_rule_versions_number'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_automation_rule_versions_tenant_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_automation_rule_ve_brand_00ad3627')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'automation_rule_id'], 'fk_automation_rule_ve_automation_rule_i_4f9a20ee')->references(['organisation_id', 'brand_id', 'id'])->on('automation_rules')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rule_versions');
    }
};
