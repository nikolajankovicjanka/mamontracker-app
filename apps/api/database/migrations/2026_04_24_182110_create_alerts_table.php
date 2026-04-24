<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            $table->foreignId('gps_device_id')
                ->nullable()
                ->constrained('gps_devices')
                ->nullOnDelete();

            $table->string('type');
            $table->string('severity')->default('info');
            $table->string('title');
            $table->text('message');

            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'type'], 'alerts_tenant_type_idx');
            $table->index(['tenant_id', 'is_read'], 'alerts_tenant_read_idx');
            $table->index(['tenant_id', 'severity'], 'alerts_tenant_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
