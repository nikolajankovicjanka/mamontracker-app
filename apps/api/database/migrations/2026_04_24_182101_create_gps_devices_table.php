<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            $table->string('provider')->default('traccar');
            $table->string('device_name')->nullable();
            $table->string('model')->nullable();

            $table->string('imei')->unique();
            $table->unsignedBigInteger('traccar_device_id')->nullable()->unique();

            $table->string('sim_number')->nullable();
            $table->boolean('is_active')->default(true);

            $table->json('last_payload')->nullable();
            $table->timestamp('last_sync_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'is_active'], 'gps_devices_tenant_active_idx');
            $table->index(['tenant_id', 'vehicle_id'], 'gps_devices_tenant_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_devices');
    }
};
