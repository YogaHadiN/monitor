<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'sunat_bot_enabled')) {
                $table->boolean('sunat_bot_enabled')->default(false)->after('image_bot_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'sunat_bot_enabled')) {
                $table->dropColumn('sunat_bot_enabled');
            }
        });
    }
};
