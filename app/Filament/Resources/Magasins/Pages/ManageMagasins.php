<?php

namespace App\Filament\Resources\Magasins\Pages;

use App\Filament\Resources\Magasins\MagasinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMagasins extends ManageRecords
{
    protected static string $resource = MagasinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
