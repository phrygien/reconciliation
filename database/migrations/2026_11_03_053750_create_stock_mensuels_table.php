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
        Schema::create('stock_mensuels', function (Blueprint $table) {
            $table->id();
            $table->string('periode');
            $table->string('code_stock', 5)->comment('ADV ou COSMA');
            $table->string('parkod', 12);

            $table->integer('qte_ouverture')->default(0);
            $table->decimal('val_ouverture', 14, 4)->default(0);

            $table->integer('qte_in')->default(0)->comment('Sell-in');
            $table->decimal('val_in', 14, 4)->default(0);

            $table->integer('qte_out')->default(0)->comment('Sell-out');
            $table->decimal('val_out', 14, 4)->default(0);

            $table->integer('qte_stk')->default(0)->comment('Écarts d\'inventaire');
            $table->decimal('val_stk', 14, 4)->default(0);

            $table->integer('qte_cloture')->default(0);
            $table->decimal('val_cloture', 14, 4)->default(0);
            $table->decimal('pamp_cloture', 12, 4)->default(0)->comment('PAMP à la clôture');

            $table->timestamps();

            $table->foreign('code_stock')->references('code_stock')->on('societes');
            $table->foreign('parkod')->references('parkod')->on('articles');
            $table->index('parkod');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mensuels');
    }
};
