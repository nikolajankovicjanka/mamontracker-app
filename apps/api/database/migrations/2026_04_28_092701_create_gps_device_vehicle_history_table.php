<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_device_vehicle_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('gps_device_id')
                ->constrained('gps_devices')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'gps_device_id'], 'gps_hist_tenant_device_idx');
            $table->index(['tenant_id', 'vehicle_id'], 'gps_hist_tenant_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_device_vehicle_history');
    }
};
