<?php

namespace App\Filament\Resources\EtatStockWinparves\Pages;

use App\Filament\Resources\EtatStockWinparves\EtatStockWinparfResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEtatStockWinparves extends ListRecords
{
    protected static string $resource = EtatStockWinparfResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }
}
