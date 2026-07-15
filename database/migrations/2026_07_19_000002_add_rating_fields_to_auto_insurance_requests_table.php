<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compulsory motor TPL in Armenia rates on more than just owner type -
     * engine power, driver experience, and accident-free history
     * (bonus-malus) all move the real premium. Previously only owner_type
     * and contract_term_months existed, so every provider's mock quote
     * differed by nothing but an arbitrary per-partner multiplier - these
     * give MockInsuranceProvider real inputs to work with (see its updated
     * quote() formula), and are exactly the fields a genuine insurer API
     * integration would need too.
     */
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
