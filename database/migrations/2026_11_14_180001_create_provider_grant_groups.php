<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_grant_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_family', 30);
            $table->string('application_id', 128);
            $table->char('actor_digest', 64);
            $table->timestamps();
            $table->unique(['provider_family', 'application_id', 'actor_digest'], 'uq_grant_family_app_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_grant_groups');
    }
};
