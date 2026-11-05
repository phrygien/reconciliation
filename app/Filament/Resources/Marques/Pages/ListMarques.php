<?php

namespace App\Filament\Resources\Marques\Pages;

use App\Filament\Resources\Marques\MarqueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarques extends ListRecords
{
    protected static string $resource = MarqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
