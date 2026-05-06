<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bot_pending_buffers')) {
            Schema::create('bot_pending_buffers', function (Blueprint $table) {
                $table->id();
                $table->string('phone')->unique();
                $table->string('provider', 32)->default('watzap');
                $table->json('messages');
                $table->unsignedBigInteger('version')->default(0);
                $table->timestamp('last_received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_pending_buffers');
    }
};
