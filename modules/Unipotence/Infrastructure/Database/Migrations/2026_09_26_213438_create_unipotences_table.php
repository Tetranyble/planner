<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Unipotence\Domain\Enums\UnipotenceState;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection((string) config('unipotence.connection'))->create('unipotences', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->string('key');
            $table->char('request_hash', 64);
            $table->string('reference')->nullable();
            $table->uuid('claim_token');
            $table->string('state', 20)->default(UnipotenceState::Claimed->value);
            $table->smallInteger('response_status')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->mediumText('response_headers')->nullable();
            $table->timestamp('locked_until');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('indeterminate_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'key']);
            $table->index(['state', 'locked_until']);
            $table->index(['state', 'completed_at']);
            $table->index(['state', 'indeterminate_at']);
            $table->index(['scope', 'reference', 'state']);
        });
    }

    public function down(): void
    {
        Schema::connection((string) config('unipotence.connection'))->dropIfExists('unipotences');
    }
};
