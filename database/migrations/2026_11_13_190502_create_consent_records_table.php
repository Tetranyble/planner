<?php

use App\Enums\Growth\ConsentChannel;
use App\Enums\Growth\ConsentLegalBasis;
use App\Enums\Growth\ConsentSourceKind;
use App\Enums\Growth\ConsentState;
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
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('lead_contact_id');

            $table->string('channel', 20)->default(ConsentChannel::EMAIL->value);
            $table->string('purpose');
            $table->string('consent_state', 20)->default(ConsentState::GRANTED->value);
            $table->string('legal_basis', 30)->default(ConsentLegalBasis::CONSENT->value);
            $table->string('source_kind', 30)->default(ConsentSourceKind::FORM->value);
            $table->string('evidence_ref')->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->foreign('organisation_id')->references('id')->on('organisations')
                ->onDelete('restrict');

            $table->foreign('brand_id')->references('id')->on('brands')
                ->onDelete('restrict');
            $table->foreign('lead_contact_id')->references('id')->on('lead_contacts')
                ->onDelete('restrict');

            $table->unique(['organisation_id', 'brand_id', 'id'], 'uq_consent_records_brand_id');
            $table->foreign(['organisation_id', 'brand_id'], 'fk_consent_records_brand_c97dedbd')->references(['organisation_id', 'id'])->on('brands')->restrictOnDelete();
            $table->foreign(['organisation_id', 'brand_id', 'lead_contact_id'], 'fk_consent_records_lead_contact_id_3670b786')->references(['organisation_id', 'brand_id', 'id'])->on('lead_contacts')->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
