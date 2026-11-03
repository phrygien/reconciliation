<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

#[Fillable(['mag_code', 'libelle', 'societe_id', 'type_magasin', 'est_web'])]
class Magasin extends Model
{
    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    protected function casts(): array
    {
        return [
            'est_web' => 'boolean',
        ];
    }

    public function mouvementsOrigine(): HasMany
    {
        return $this->hasMany(MouvementBrut::class, 'mag_orig', 'mag_code');
    }

    public function mouvementsDestination(): HasMany
    {
        return $this->hasMany(MouvementBrut::class, 'mag_dest', 'mag_code');
    }

    public function scopeDeType(Builder $query, string $type): Builder
    {
        return $query->where('type_magasin', $type);
    }

    public function scopeWeb(Builder $query): Builder
    {
        return $query->where('est_web', true);
    }
}
