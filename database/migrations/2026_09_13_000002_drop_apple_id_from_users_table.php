<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Removes the Apple sign-in column along with the rest of that feature.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'apple_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_apple_id_unique');
            $table->dropColumn('apple_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('apple_id')->nullable()->unique()->after('google_id');
        });
    }
};
