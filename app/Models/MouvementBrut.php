<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_id',
    'sequence_src',
    'in_out',
    'societe_src',
    'date_mvt',
    'typ_mvt',
    'mag_orig',
    'mag_dest',
    'parkod',
    'ean',
    'qte',
    'montant',
    'paht',
    'pamp',
    'punet',
    'id_transac',
    'hash_ligne',
])]
class MouvementBrut extends Model
{
    protected $table = 'mouvement_bruts';

    protected function casts(): array
    {
        return [
            'sequence_src' => 'integer',
            'date_mvt'     => 'datetime',
            'qte'          => 'integer',
            'montant'      => 'decimal:4',
            'paht'         => 'decimal:4',
            'pamp'         => 'decimal:4',
            'punet'        => 'decimal:4',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function typeMouvement(): BelongsTo
    {
        return $this->belongsTo(TypeMouvement::class, 'typ_mvt', 'type_mouvement');
    }

    public function magasinOrigine(): BelongsTo
    {
        return $this->belongsTo(Magasin::class, 'mag_orig', 'mag_code');
    }

    public function magasinDestination(): BelongsTo
    {
        return $this->belongsTo(Magasin::class, 'mag_dest', 'mag_code');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'parkod', 'parkod');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(MouvementAffecte::class);
    }
}
