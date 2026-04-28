<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_devices', function (Blueprint $table) {
            // stari index iz prve migracije
            $table->dropIndex('gps_devices_tenant_vehicle_idx');

            // capabilities za buduće feature-e uređaja
            $table->json('capabilities')->nullable()->after('sim_number');

            // jedan uređaj aktivno na jednom vozilu,
            // a jedno vozilo aktivno ima jedan uređaj
            $table->unique('vehicle_id', 'gps_devices_vehicle_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('gps_devices', function (Blueprint $table) {
            $table->dropUnique('gps_devices_vehicle_id_unique');
            $table->dropColumn('capabilities');

            $table->index(['tenant_id', 'vehicle_id'], 'gps_devices_tenant_vehicle_idx');
        });
    }
};
