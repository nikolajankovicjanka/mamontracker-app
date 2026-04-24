<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('production_year')->nullable();

            $table->string('license_plate');
            $table->string('vin')->nullable();

            $table->date('registration_expiry_date')->nullable();

            $table->decimal('current_mileage', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();

            $table->decimal('last_known_lat', 10, 7)->nullable();
            $table->decimal('last_known_lng', 10, 7)->nullable();
            $table->timestamp('last_position_at')->nullable();
            $table->decimal('last_speed_kph', 8, 2)->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'license_plate'], 'vehicles_tenant_plate_unique');
            $table->index(['tenant_id', 'status'], 'vehicles_tenant_status_idx');
            $table->index(['tenant_id', 'registration_expiry_date'], 'vehicles_tenant_reg_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
