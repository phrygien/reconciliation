<?php

namespace App\Filament\Resources\MouvementBruts\Pages;

use App\Filament\Resources\MouvementBruts\MouvementBrutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMouvementBruts extends ListRecords
{
    protected static string $resource = MouvementBrutResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }
}
