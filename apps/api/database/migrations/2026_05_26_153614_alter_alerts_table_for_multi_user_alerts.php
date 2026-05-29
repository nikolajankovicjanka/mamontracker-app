<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn([
                'is_read',
                'seen_at',
            ]);

            $table->index(['tenant_id', 'sent_at'], 'alerts_tenant_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->boolean('is_read')->default(false)->after('message');
            $table->timestamp('seen_at')->nullable()->after('sent_at');

            $table->dropIndex('alerts_tenant_sent_idx');
        });
    }
};
