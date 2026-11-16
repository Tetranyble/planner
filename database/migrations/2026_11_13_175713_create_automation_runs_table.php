<?php

use App\Enums\Automation\AutomationRunStatus;
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
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('automation_rule_version_id');

            $table->string('trigger_event_key');
            $table->string('status', 20)->default(AutomationRunStatus::QUEUED->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('lease_owner')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->json('result_summary');
            $table->string('error_code')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('automation_rule_version_id')->references('id')->on('automation_rule_versions')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'automation_rule_version_id', 'trigger_event_key'],
                'uq_automation_runs_trigger'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_automation_runs_brand_id');
            $table->index(['status', 'lease_until'], 'idx_automation_runs_lease');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_automation_runs_brand_63ef4745')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'automation_rule_version_id'], 'fk_automation_runs_automation_rule_v_c7696dbb')->references(['organisation_id', 'brand_id', 'id'])->on('automation_rule_versions')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
