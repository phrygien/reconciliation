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
        Schema::create('magasins', function (Blueprint $table) {
            $table->id();
            $table->string('mag_code', 10)->unique();
            $table->string('libelle');
            $table->foreignId('societe_id')->constrained(); // reference societe
            $table->enum('type_magasin', ['DEPOT', 'PHYSIQUE', 'WEB', 'SURSTOCK'])
                ->comment('DEPOT | PHYSIQUE | WEB | SURSTOCK');
            $table->boolean('est_web')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('magasins');
    }
};
