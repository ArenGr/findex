<?php

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
        Schema::create('auto_insurance_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_insurance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->decimal('premium_amount', 10, 2)->nullable();
            $table->string('premium_currency', 3)->nullable();
            $table->unsignedTinyInteger('policy_term_months')->nullable();
            $table->text('coverage_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['auto_insurance_request_id', 'organization_id'], 'auto_insurance_quotes_request_org_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_insurance_quotes');
    }
};
