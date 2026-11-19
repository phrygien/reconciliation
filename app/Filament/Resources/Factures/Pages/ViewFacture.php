<?php

namespace App\Filament\Resources\Factures\Pages;

use App\Filament\Resources\Factures\Actions\FacturePdfActions;
use App\Filament\Resources\Factures\FactureResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewFacture extends ViewRecord
{
    protected static string $resource = FactureResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            FacturePdfActions::apercu(),
            FacturePdfActions::telecharger(),
            FacturePdfActions::generer(),
            //EditAction::make(),
        ];
    }
}
