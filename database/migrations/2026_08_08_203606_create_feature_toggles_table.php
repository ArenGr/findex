<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_toggles', function (Blueprint $table) {
            $table->id();
            // Matches a bank product category slug (see
            // OfferController::categories()). Rows are seeded, not created
            // from the admin panel - the set of toggles is defined by what
            // the app actually has pages for.
            $table->string('key')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_toggles');
    }
};
