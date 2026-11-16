<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_guard_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->index();
            $table->string('level')->default('error');
            $table->string('title');
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('mode')->default('normal')->index();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('paused_until')->nullable();
            $table->string('user_id', 64)->nullable();
            $table->string('user_email')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('route')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_guard_reports');
    }
};
