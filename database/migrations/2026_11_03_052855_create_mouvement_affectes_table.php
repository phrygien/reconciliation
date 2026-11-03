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
        Schema::create('mouvement_affectes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mouvement_brut_id')
                ->constrained('mouvement_bruts')
                ->cascadeOnDelete();
            $table->string('code_stock', 5)->comment('ADV ou COSMA');
            $table->integer('qte')->comment('Signée : + entrée, - sortie');
            $table->decimal('valeur', 14, 4)->comment('Valeur de l\'affectation');
            $table->string('mode_affect', 10)->comment('AUTO | REPARTI | MANUEL');
            $table->date('date_comptable')->nullable()->comment('Pour la clôture mensuelle');
            $table->timestamps();

            $table->foreign('code_stock')->references('code_stock')->on('societes');

            $table->index('mouvement_brut_id');
            $table->index(['code_stock', 'date_comptable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvement_affectes');
    }
};
