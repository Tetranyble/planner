<?php

use App\Enums\Growth\LeadContactKind;
use App\Enums\Growth\LeadContactStatus;
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
        Schema::create('lead_contacts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('lead_id');

            $table->string('full_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('contact_kind', 30)->default(LeadContactKind::PERSON->value);
            $table->string('preferred_channel', 20)->nullable();
            $table->string('status', 20)->default(LeadContactStatus::ACTIVE->value);

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');

            $table->foreign('lead_id')->references('id')->on('leads')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_lead_contacts_brand_id');

            // Replaces partial index idx_lead_contacts_email
            // (organisation_id, brand_id, lower(email)) WHERE email IS NOT NULL.
            $table->string('email_lower', 320)->nullable();
            $table->index(['organisation_id', 'brand_id', 'email_lower'], 'idx_lead_contacts_email');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_lead_contacts_brand_7bf4ecf0')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_id'], 'fk_lead_contacts_lead_id_0b56f021')->references(['organisation_id', 'brand_id', 'id'])->on('leads')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_contacts');
    }
};
