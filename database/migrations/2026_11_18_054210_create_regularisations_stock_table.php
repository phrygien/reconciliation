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
        Schema::create('regularisations_stock', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 7);               // période d'ouverture corrigée (P2)
            $table->string('periode_origine', 7);       // période où l'écart est né (P1)
            $table->string('parkod', 20);
            $table->string('code_stock_debiteur', 10);  // celui qui a pris (ADV)
            $table->string('code_stock_crediteur', 10); // celui à qui on a pris (COSMA)
            $table->integer('qte');                     // quantité transférée, positive
            $table->decimal('pamp', 12, 4);             // PAMP de valorisation
            $table->decimal('valeur', 14, 4);           // qte * pamp = montant à facturer
            $table->string('cas', 20);                  // COMPENSABLE_TOTAL / PARTIEL
            $table->string('statut', 20)->default('A_FACTURER'); // A_FACTURER, FACTURE, ANNULE
            $table->string('numero_facture')->nullable();
            $table->date('date_facture')->nullable();
            $table->json('avant');                      // snapshot des 2 ouvertures avant
            $table->json('apres');                      // snapshot après
            $table->foreignId('user_id')->nullable();
            $table->timestamps();

            $table->unique(['periode', 'parkod', 'code_stock_debiteur']); // évite le double traitement
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regularisations_stock');
    }
};
