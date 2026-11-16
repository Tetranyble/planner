<?php

use App\Enums\Publishing\ApprovalRequestKind;
use App\Enums\Publishing\ApprovalRequestStatus;
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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('content_target_id');

            $table->char('payload_hash', 64);
            $table->string('request_kind', 30)->default(ApprovalRequestKind::HUMAN->value);
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedInteger('required_approvals')->default(1);
            $table->string('status', 20)->default(ApprovalRequestStatus::PENDING->value);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('policy_snapshot');

            // Columns added later by the v1.3 linkage migration.
            // Kept here (nullable) so this migration matches the original schema;
            // the ALTER below in 2025_01_04_000015 will fill them in.
            $table->unsignedBigInteger('approval_policy_id')->nullable();
            $table->unsignedBigInteger('matched_rule_id')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('content_target_id')->references('id')->on('content_targets')
                ->onDelete('restrict');
            $table->foreign('approval_policy_id')->references('id')->on('brand_approval_policies')
                ->onDelete('restrict');
            $table->foreign('matched_rule_id')->references('id')->on('brand_approval_rules')
                ->onDelete('restrict');

            $table->foreign('requested_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            // Required by publication_jobs's 4-column composite FK.
            $table->unique(['organisation_id', 'brand_id', 'id', 'content_target_id'],
                'uq_approval_requests_target'
            );

            $table->index(['organisation_id', 'brand_id', 'status', 'created_at'],
                'idx_approval_requests_pending'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_approval_requests_brand_id_b5b7de30');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_approval_requests_brand_1bb3be95')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'content_target_id'], 'fk_approval_requests_content_target_id_a8509d71')->references(['organisation_id', 'brand_id', 'id'])->on('content_targets')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'approval_policy_id'], 'fk_approval_requests_approval_policy_i_ca83dcbb')->references(['organisation_id', 'brand_id', 'id'])->on('brand_approval_policies')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'matched_rule_id'], 'fk_approval_requests_matched_rule_id_59ad57b7')->references(['organisation_id', 'brand_id', 'id'])->on('brand_approval_rules')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
