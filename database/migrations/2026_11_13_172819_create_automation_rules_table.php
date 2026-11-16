<?php

use App\Enums\Automation\AutomationRuleStatus;
use App\Enums\Automation\AutomationTriggerKind;
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
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->string('name');
            $table->string('trigger_kind', 20)->default(AutomationTriggerKind::MANUAL->value);
            $table->string('status', 20)->default(AutomationRuleStatus::DRAFT->value);
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->unsignedInteger('max_runs_per_day')->default(50);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('owner_user_id')->references('id')->on('users')
                ->onDelete('restrict');

            // Required by child FKs (automation_rule_versions).
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_automation_rules_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_automation_rules_brand_c356af67')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
