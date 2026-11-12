<?php

namespace App\Filament\Resources\EtatStockWinparves\Pages;

use App\Filament\Resources\EtatStockWinparves\EtatStockWinparfResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEtatStockWinparf extends EditRecord
{
    protected static string $resource = EtatStockWinparfResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
