<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dedicated profile table, not columns bolted onto users - mirrors
 * Organization (see users.organization_id / Organization::users()). This
 * table only covers the account/approval side; there's no article model
 * yet, that's a separate future feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('expertise')->nullable();
            $table->text('topics')->nullable();

            // Writers register inactive and need admin approval, same as
            // Organization - unlike organizations' original migration
            // (which defaulted true and was only forced false at
            // registration time), start this correctly from day one.
            $table->boolean('is_active')->default(false);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writers');
    }
};
