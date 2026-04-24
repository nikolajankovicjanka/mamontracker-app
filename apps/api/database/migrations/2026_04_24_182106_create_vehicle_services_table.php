<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('service_type')->default('regular_service');
            $table->date('service_date');
            $table->decimal('mileage_at_service', 12, 2);
            $table->decimal('next_service_due_km', 12, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id'], 'vehicle_services_tenant_vehicle_idx');
            $table->index(['tenant_id', 'next_service_due_km'], 'vehicle_services_tenant_due_km_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_services');
    }
};
