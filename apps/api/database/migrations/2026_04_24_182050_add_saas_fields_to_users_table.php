<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->string('role')
                ->default('tenant_user')
                ->after('password');

            $table->boolean('is_active')
                ->default(true)
                ->after('role');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');

            $table->index(['tenant_id', 'role'], 'users_tenant_role_idx');
            $table->index(['tenant_id', 'is_active'], 'users_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tenant_role_idx');
            $table->dropIndex('users_tenant_active_idx');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['role', 'is_active', 'last_login_at']);
        });
    }
};
