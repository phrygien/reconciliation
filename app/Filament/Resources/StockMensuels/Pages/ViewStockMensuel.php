<?php

namespace App\Filament\Resources\StockMensuels\Pages;

use App\Filament\Resources\StockMensuels\StockMensuelResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMensuel extends ViewRecord
{
    protected static string $resource = StockMensuelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
