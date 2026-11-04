<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('periodes', function (Blueprint $table) {
            $table->string('periode', 7)->primary()->comment('Format AAAA-MM, ex. 2026-09');
            $table->string('statut', 10)->default('OUVERTE')->comment('OUVERTE | CLOTURE');
            $table->dateTime('cloture_le')->nullable()->comment('Date de clôture mensuelle');
            $table->timestamps();

            $table->index('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodes');
    }
};
