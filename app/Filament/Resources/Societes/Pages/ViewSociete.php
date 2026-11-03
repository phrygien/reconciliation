<?php

namespace App\Filament\Resources\Societes\Pages;

use App\Filament\Resources\Societes\SocieteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSociete extends ViewRecord
{
    protected static string $resource = SocieteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
