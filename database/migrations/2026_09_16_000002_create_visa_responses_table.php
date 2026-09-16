<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('response_token', 64)->unique();
            $table->string('status', 20)->default('pending');

            // One price per agency: the fee it charges to handle this visa.
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->unsignedSmallInteger('processing_days')->nullable();
            $table->text('reply_text')->nullable();

            $table->timestamp('responded_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['visa_request_id', 'organization_id']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_responses');
    }
};
