<?php

use App\Models\Societe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const METRIQUES = [
        'qte'  => 0, // quantité : 0 décimale
        'in'   => 0,
        'out'  => 0,
        'stk'  => 0,
        'vin'  => 2, // valeur : 2 décimales
        'vout' => 2,
        'val'  => 2,
    ];

    public function up(): void
    {
        $nb = Societe::query()->count();

        Schema::create('stock_mensuels_resume', function (Blueprint $table) use ($nb) {
            $table->id();
            $table->string('periode', 20);
            $table->string('parkod', 50);
            $table->boolean('a_stock_negatif')->default(false);

            // s0, s1, ... une par société, puis t = total
            $prefixes = array_merge(
                array_map(fn ($i) => "s{$i}", range(0, max($nb - 1, 0))),
                ['t']
            );

            foreach ($prefixes as $p) {
                foreach (self::METRIQUES as $alias => $decimales) {
                    $table->decimal("{$p}_{$alias}", 18, $decimales)->default(0);
                }
            }

            $table->unique(['periode', 'parkod']);
            $table->index('parkod');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mensuels_resume');
    }
};
