<?php

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
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
        Schema::create('organisation_preference_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_type')->default(NotificationEventType::PUBLICATION_FAILED->value);
            $table->string('channel')->default(NotificationChannel::EMAIL->value);
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('role_id')->nullable();

            $table->unsignedBigInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('cascade');
            $table->unique(['organisation_id', 'event_type', 'channel'], 'uq_org_notification_rule');

            $table->foreign('role_id')->references('id')->on('roles')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_preference_rules');
    }
};
