<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom_fichier', 'importe_le', 'date_min', 'date_max', 'nb_ligne', 'statut'])]
class ImportBatch extends Model
{
    //
}
