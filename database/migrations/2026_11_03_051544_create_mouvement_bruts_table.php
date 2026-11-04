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
        Schema::create('mouvement_bruts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches');
            $table->integer('sequence_src')->nullable()->comment('SEQUENCE du fichier (non stable)');
            $table->string('in_out', 3);
            $table->string('societe_src', 10)->comment('SOCIETE tel qu\'exporté');
            $table->dateTime('date_mvt')->nullable()->comment('Converti depuis 20260901 1230');
            $table->string('typ_mvt', 5)->nullable();
            $table->char('mag_orig', 10)->nullable();
            $table->char('mag_dest', 10)->nullable();
            $table->string('parkod', 12)->nullable();
            $table->integer('qte')->nullable()->comment('Quantité signée');
            $table->decimal('montant', 14, 4)->nullable();
            $table->decimal('paht', 12, 4)->nullable()->comment('Prix d\'achat hors taxe');
            $table->decimal('pamp', 12, 4)->nullable()->comment('Prix d\'achat moyen pondéré');
            $table->decimal('punet', 12, 4)->nullable()->comment('Prix unitaire net');
            $table->string('id_transac', 60)->nullable();
            $table->char('hash_ligne', 64)->unique()->comment('Anti-doublon à la ré-import');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvement_bruts');
    }
};
