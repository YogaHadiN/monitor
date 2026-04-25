<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('no_telp')->index();
            $table->string('flow_type', 50);
            $table->string('current_step', 80);
            $table->json('collected_data')->nullable();
            $table->boolean('escalated_to_human')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['no_telp', 'flow_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};
