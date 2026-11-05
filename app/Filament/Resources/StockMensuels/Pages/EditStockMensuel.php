<?php

namespace App\Filament\Resources\StockMensuels\Pages;

use App\Filament\Resources\StockMensuels\StockMensuelResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStockMensuel extends EditRecord
{
    protected static string $resource = StockMensuelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
