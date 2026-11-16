<?php

use App\Enums\Operation\AuditActorKind;
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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->string('actor_kind', 20)->default(AuditActorKind::AGENT->value);
            $table->string('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('resource_kind', 50);
            $table->string('resource_id')->nullable();
            $table->unsignedBigInteger('brand_ref')->nullable();
            $table->string('request_id')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->json('details');

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'occurred_at'], 'idx_audit_logst');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
