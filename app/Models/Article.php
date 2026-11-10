<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parkod', 'marque_id', 'ean', 'description', 'paht', 'actif'])]
class Article extends Model
{
    public function marque(): BelongsTo
    {
        return $this->belongsTo(Marque::class);
    }

    public function stockMensuels(): HasMany
    {
        return $this->hasMany(StockMensuel::class, 'parkod', 'parkod');
    }

    public function mouvementBruts(): HasMany
    {
        return $this->hasMany(MouvementBrut::class, 'parkod', 'parkod');
    }
}
