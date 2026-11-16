<?php

use App\Enums\Operation\NotificationState;
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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->string('kind', 50);
            $table->string('subject');
            $table->text('body');
            $table->string('related_ref')->nullable();
            $table->string('state', 20)->default(NotificationState::PENDING->value);
            $table->string('email_provider_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');
            $table->foreign('recipient_user_id')->references('id')->on('users')
                ->onDelete('restrict');

            $table->index(['organisation_id', 'recipient_user_id', 'created_at'], 'idx_notifications_inbox');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
