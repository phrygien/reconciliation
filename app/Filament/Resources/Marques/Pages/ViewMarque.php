<?php

namespace App\Filament\Resources\Marques\Pages;

use App\Filament\Resources\Marques\MarqueResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMarque extends ViewRecord
{
    protected static string $resource = MarqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
