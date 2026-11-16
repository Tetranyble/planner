<?php

use App\Enums\StateEnum;
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
        Schema::create('organisation_memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('state', 30)->default(StateEnum::ACTIVE->value);
            $table->unsignedBigInteger('invited_by_user_id')->nullable();
            $table->timestamp('joined_at')->nullable();

            $table->primary(['organisation_id', 'user_id']);

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')->restrictOnDelete();
            $table->foreign('user_id')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('role_id')
                ->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('invited_by_user_id')
                ->references('id')->on('users')->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_memberships');
    }
};
