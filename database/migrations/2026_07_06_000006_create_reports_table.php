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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('review_count')->default(0);
            $table->decimal('positive_pct', 5, 2)->nullable();
            $table->decimal('neutral_pct', 5, 2)->nullable();
            $table->decimal('negative_pct', 5, 2)->nullable();
            $table->text('summary')->nullable();
            $table->json('themes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
