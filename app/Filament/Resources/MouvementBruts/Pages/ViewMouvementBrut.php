<?php

namespace App\Filament\Resources\MouvementBruts\Pages;

use App\Filament\Resources\MouvementBruts\MouvementBrutResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMouvementBrut extends ViewRecord
{
    protected static string $resource = MouvementBrutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //EditAction::make(),
        ];
    }
}
