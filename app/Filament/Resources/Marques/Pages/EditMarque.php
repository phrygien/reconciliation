<?php

namespace App\Filament\Resources\Marques\Pages;

use App\Filament\Resources\Marques\MarqueResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMarque extends EditRecord
{
    protected static string $resource = MarqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
