<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['parkod', 'marque_id', 'description', 'paht', 'actif'])]
class Article extends Model
{
    public function marque(): BelongsTo
    {
        return $this->belongsTo(Marque::class);
    }
}
