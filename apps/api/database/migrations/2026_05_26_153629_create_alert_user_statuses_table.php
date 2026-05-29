<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_user_statuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alert_id')
                ->constrained('alerts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('is_read')->default(false);
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->unique(['alert_id', 'user_id'], 'alert_user_statuses_alert_user_unique');
            $table->index(['user_id', 'is_read'], 'alert_user_statuses_user_read_idx');
            $table->index(['alert_id', 'is_read'], 'alert_user_statuses_alert_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_user_statuses');
    }
};
