<?php

namespace App\Filament\Resources\Periodes\Pages;

use App\Filament\Resources\Periodes\PeriodeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPeriode extends EditRecord
{
    protected static string $resource = PeriodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
