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
        Schema::create('type_mouvements', function (Blueprint $table) {
            $table->id();
            $table->string('type_mouvement', 5)->unique()->comment('VA | AA | TM | RF/RE | IF | IC');
            $table->enum('sens', ['IN', 'OUT', 'STK']);
            $table->enum('categorie', ['SELL_IN', 'SELL_OUT', 'REASSORT', 'TRANSFERT', 'INVENTAIRE']);
            $table->enum('regle_affect', ['PAR_MAG_DEST', 'PAR_MAG_ORIG', 'PAR_SOCIETE_PAYEUSE', 'REPARTI']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_mouvements');
    }
};
