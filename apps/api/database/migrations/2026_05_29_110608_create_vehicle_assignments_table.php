<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('assignment_type')->default('primary');
            $table->timestamp('assigned_from');
            $table->timestamp('assigned_until')->nullable();
            $table->timestamp('unassigned_at')->nullable();

            $table->string('status')->default('active');
            $table->text('notes')->nullable();

            $table->decimal('start_mileage', 12, 2)->nullable();
            $table->decimal('end_mileage', 12, 2)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'user_id'], 'vehicle_assignments_tenant_user_idx');
            $table->index(['tenant_id', 'vehicle_id'], 'vehicle_assignments_tenant_vehicle_idx');
            $table->index(['tenant_id', 'status'], 'vehicle_assignments_tenant_status_idx');
            $table->index(['tenant_id', 'assigned_from'], 'vehicle_assignments_tenant_assigned_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_assignments');
    }
};
