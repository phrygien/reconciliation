<?php

namespace App\Filament\Resources\MouvementBruts\Pages;

use App\Filament\Resources\MouvementBruts\MouvementBrutResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMouvementBrut extends EditRecord
{
    protected static string $resource = MouvementBrutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
