<?php

use App\Enums\Connection\SocialAccountCapabilityMode;
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
        Schema::create('social_account_capabilities', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('social_account_id');

            $table->string('capability');
            $table->string('mode', 20)->default(SocialAccountCapabilityMode::AUTOMATIC->value);
            $table->text('reason')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamp('valid_until')->nullable();

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('social_account_id')->references('id')->on('social_accounts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'social_account_id', 'capability'], 'uq_social_account_capabilities');
            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_social_account_cap_brand_id_7f5576c0');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_social_account_cap_brand_40c08956')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'social_account_id'], 'fk_social_account_cap_social_account_id_13138623')->references(['organisation_id', 'brand_id', 'id'])->on('social_accounts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_account_capabilities');
    }
};
