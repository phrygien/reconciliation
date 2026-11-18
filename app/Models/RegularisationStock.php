<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'periode',              // période d'ouverture corrigée (P2)
    'periode_origine',      // période où l'écart est né (P1)
    'parkod',
    'code_stock_debiteur',  // celui qui a pris (ADV)
    'code_stock_crediteur', // celui à qui on a pris (COSMA)
    'qte',                  // quantité transférée, positive
    'pamp',                 // PAMP de valorisation
    'valeur',               // qte * pamp = montant à facturer
    'cas',                  // COMPENSABLE_TOTAL / COMPENSABLE_PARTIEL
    'statut',               // A_FACTURER, FACTURE, ANNULE
    'facture_id',           // relier au facture
    'avant',                // snapshot des 2 ouvertures avant
    'apres',                // snapshot après
    'user_id',
])]
#[Table('regularisations_stock')]
class RegularisationStock extends Model
{
    public const STATUT_A_FACTURER = 'A_FACTURER';
    public const STATUT_FACTURE    = 'FACTURE';
    public const STATUT_ANNULE     = 'ANNULE';

    public const CAS_TOTAL   = 'COMPENSABLE_TOTAL';
    public const CAS_PARTIEL = 'COMPENSABLE_PARTIEL';

    protected function casts(): array
    {
        return [
            'qte'          => 'integer',
            'pamp'         => 'decimal:4',
            'valeur'       => 'decimal:4',
            'facture_id'       => 'int',
            'avant'        => 'array',
            'apres'        => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // les 2 lignes stock_mensuel (débiteur + créditeur) modifiées par cette régularisation
    public function stocks(): HasMany
    {
        return $this->hasMany(StockMensuel::class, 'regularisation_id');
    }

    public function scopeAFacturer(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_A_FACTURER);
    }

    public function scopePourPeriode(Builder $query, string $periode): Builder
    {
        return $query->where('periode', $periode);
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class, 'facture_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'parkod', 'parkod');
    }
}
