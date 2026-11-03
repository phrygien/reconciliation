<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type_mouvement', 'sens', 'categorie', 'regle_affect'])]
class TypeMouvement extends Model
{
    protected $primaryKey = 'type_mouvement';

    protected $keyType = 'string';

    public $incrementing = false;
}
