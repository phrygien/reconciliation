<?php

namespace App\Filament\Resources\MouvementAffectes\Pages;

use App\Filament\Resources\MouvementAffectes\MouvementAffecteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMouvementAffecte extends EditRecord
{
    protected static string $resource = MouvementAffecteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
