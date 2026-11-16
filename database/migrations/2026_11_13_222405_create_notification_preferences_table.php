<?php

use App\Enums\Operation\NotificationPreferenceChannel;
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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('notification_kind', 50);
            $table->string('channel', 20)->default(NotificationPreferenceChannel::EMAIL->value);
            $table->boolean('enabled')->default(true);

            // organisation_memberships is expected to have a composite PK
            // (organisation_id, user_id) or a UNIQUE KEY on those columns.
            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'user_id', 'notification_kind', 'channel'], 'uq_notification_preferences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
