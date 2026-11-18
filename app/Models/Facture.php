<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'numero', 'lot', 'periode', 'facturer_a', 'facture_par',
    'nb_articles', 'total_qte', 'total',
    'statut', 'date_facture', 'pdf_path', 'user_id',
])]
#[Table('facture')]
class Facture extends Model
{
    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_EMISE     = 'EMISE';
    public const STATUT_ANNULEE   = 'ANNULEE';

    protected function casts(): array
    {
        return [
            'nb_articles'  => 'integer',
            'total_qte'    => 'integer',
            'total'        => 'decimal:2',
            'date_facture' => 'date',
        ];
    }

    // les lignes de régularisation = détails de la facture
    public function lignes(): HasMany
    {
        return $this->hasMany(RegularisationStock::class, 'facture_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}