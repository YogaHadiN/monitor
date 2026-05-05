<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sunat_qnas')) {
            return;
        }

        Schema::create('sunat_qnas', function (Blueprint $table) {
            $table->id();
            $table->json('patterns');
            $table->text('answer');
            $table->unsignedSmallInteger('urutan')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_qnas');
    }
};
