<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('destination_country', 2);
            $table->string('locale', 5);
            $table->timestamps();

            $table->unique(['email', 'destination_country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_alerts');
    }
};
