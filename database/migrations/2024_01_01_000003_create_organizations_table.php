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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type'); // 'bank', 'telecom', 'insurance', etc.
            $table->string('website');
            $table->string('logo')->nullable();
            $table->string('country_code')->default('AM'); // ISO 3166-1 alpha-2
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
