<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('bot_sessions', 'requires_special_handling')) {
                $table->boolean('requires_special_handling')
                    ->default(false)
                    ->after('is_complete');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('bot_sessions', 'requires_special_handling')) {
                $table->dropColumn('requires_special_handling');
            }
        });
    }
};
