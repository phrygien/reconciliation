<?php

namespace App\Filament\Resources\Factures\Pages;

use App\Filament\Resources\Factures\FactureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacture extends CreateRecord
{
    protected static string $resource = FactureResource::class;
}
