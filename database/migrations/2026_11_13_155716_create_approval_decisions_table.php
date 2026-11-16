<?php

use App\Enums\Publishing\ApprovalDecision;
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
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('approval_request_id');
            $table->unsignedBigInteger('approver_user_id');

            $table->string('decision', 10)->default(ApprovalDecision::PENDING->value);
            $table->timestamp('decided_at')->useCurrent();
            $table->text('comment')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')
                ->onDelete('restrict');

            $table->foreign('approver_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(
                ['organisation_id', 'approval_request_id', 'approver_user_id'],
                'uq_approval_decisions_reviewer'
            );
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_approval_decisions_brand_id_9b8f86e8');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_approval_decisions_brand_edef3445')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'approval_request_id'], 'fk_approval_decisions_approval_request__0a67c2f7')->references(['organisation_id', 'brand_id', 'id'])->on('approval_requests')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
