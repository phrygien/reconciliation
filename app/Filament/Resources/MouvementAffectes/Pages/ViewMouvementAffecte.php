<?php

namespace App\Filament\Resources\MouvementAffectes\Pages;

use App\Filament\Resources\MouvementAffectes\MouvementAffecteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMouvementAffecte extends ViewRecord
{
    protected static string $resource = MouvementAffecteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
