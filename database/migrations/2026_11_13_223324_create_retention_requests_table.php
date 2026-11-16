<?php

use App\Enums\Operation\RetentionRequestKind;
use App\Enums\Operation\RetentionRequestState;
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
        Schema::create('retention_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->string('request_kind', 20)->default(RetentionRequestKind::RESTRICT->value);
            $table->string('subject_kind', 30);
            $table->string('subject_ref');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('state', 20)->default(RetentionRequestState::RECEIVED->value);
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('requested_by_user_id')->references('id')->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retention_requests');
    }
};
