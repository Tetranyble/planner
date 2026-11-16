<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_data_deletion_review_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('reviewed_by_user_id');
            $table->string('disposition', 30); // CLEARED | RETAINED_LEGAL
            $table->string('legal_basis', 50)->nullable();
            $table->text('explanation');
            $table->string('evidence_reference', 512);
            $table->timestamp('review_again_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique('target_id', 'uq_meta_review_decision_once');
            $table->foreign(['organisation_id', 'target_id'], 'fk_meta_review_target')
                ->references(['organisation_id', 'id'])->on('meta_data_deletion_targets')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id', 'fk_meta_review_user')->references('id')->on('users')->restrictOnDelete();
            $table->index(['organisation_id', 'created_at'], 'idx_meta_review_recent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_data_deletion_review_decisions');
    }
};
