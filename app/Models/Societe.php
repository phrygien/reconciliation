<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code_stock', 'libelle'])]
class Societe extends Model
{
    public function magasins(): HasMany
    {
        return $this->hasMany(Magasin::class);
    }
}
