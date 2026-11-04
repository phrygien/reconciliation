<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_winparf_id',
    'code_stock',
    'qte',
    'valeur',
    'date_comptable',
    'mouvement_brut_id'
])]
#[Table('etat_stock_winparf')]
class EtatStockWinparf extends Model
{
    protected $primaryKey = 'stock_winparf_id';

    protected function casts(): array
    {
        return [
            'qte'            => 'integer',
            'valeur'         => 'decimal:4',
            'date_comptable' => 'date',
        ];
    }

    public function mouvementBrut(): BelongsTo
    {
        return $this->belongsTo(MouvementBrut::class);
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class, 'code_stock', 'code_stock');
    }

    public function scopePourSociete(Builder $query, string $codeStock): Builder
    {
        return $query->where('code_stock', $codeStock);
    }

    public function scopePourMois(Builder $query, int $annee, int $mois): Builder
    {
        $debut = \Illuminate\Support\Carbon::create($annee, $mois, 1)->startOfMonth();

        return $query->whereBetween('date_comptable', [
            $debut->toDateString(),
            $debut->copy()->endOfMonth()->toDateString(),
        ]);
    }
}
