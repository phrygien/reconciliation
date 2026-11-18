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
        Schema::create('facture', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();        // REG-202611-ADV-20261118143205
            $table->string('lot', 14);                     // YmdHis du lancement
            $table->string('periode', 7);
            $table->string('facturer_a', 10);              // société débitrice (celle qui a pris)
            $table->string('facture_par', 10);             // société créditrice (celle qui facture)
            $table->unsignedInteger('nb_articles')->default(0);
            $table->integer('total_qte')->default(0);
            $table->decimal('total', 14, 2)->default(0);   // somme des valeurs des lignes, HT
            $table->string('statut', 20)->default('BROUILLON'); // BROUILLON, EMISE, ANNULEE
            $table->date('date_facture')->nullable();      // remplie à l'émission du PDF
            $table->string('pdf_path')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();

            $table->unique(['lot', 'periode', 'facturer_a']); // une facture par lot, période et débiteur
            $table->index(['periode', 'facturer_a']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facture');
    }
};
