<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mouvement_brut_id',
    'code_stock',
    'qte',
    'valeur',
    'mode_affect',
    'date_comptable',
])]
class MouvementAffecte extends Model
{
    public const MODE_AUTO = 'AUTO';
    public const MODE_REPARTI = 'REPARTI';
    public const MODE_MANUEL = 'MANUEL';

    public const MODES = [
        self::MODE_AUTO,
        self::MODE_REPARTI,
        self::MODE_MANUEL,
    ];

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

    public function scopeMode(Builder $query, string $mode): Builder
    {
        return $query->where('mode_affect', $mode);
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
