<?php

use App\Enums\ApprovalPolicyState;
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
        Schema::create('brand_approval_policies', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');

            $table->unsignedInteger('policy_version');
            $table->text('description')->nullable();
            $table->string('state', 20)->default(ApprovalPolicyState::DRAFT->value);
            $table->boolean('external_publishing_requires_approval')->default(true);
            $table->boolean('forbid_self_approval')->default(true);
            $table->unsignedSmallInteger('default_required_approvals')->default(1); // 1-10
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('retired_at')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'policy_version'], 'uq_brand_approval_policy');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_brand_approval_pol_brand_id_a9a0208e');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_brand_approval_pol_brand_5841f5e8')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_approval_policies');
    }
};
