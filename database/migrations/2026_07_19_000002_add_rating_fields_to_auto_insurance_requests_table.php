<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_insurance_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('engine_power_hp')->nullable()->after('owner_id_number');
            $table->unsignedTinyInteger('driver_experience_years')->nullable()->after('engine_power_hp');
            $table->unsignedTinyInteger('accident_free_years')->nullable()->after('driver_experience_years');
        });
    }

    public function down(): void
    {
        Schema::table('auto_insurance_requests', function (Blueprint $table) {
            $table->dropColumn(['engine_power_hp', 'driver_experience_years', 'accident_free_years']);
        });
    }
};
