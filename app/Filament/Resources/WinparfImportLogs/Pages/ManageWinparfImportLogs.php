<?php

namespace App\Filament\Resources\WinparfImportLogs\Pages;

use App\Filament\Resources\WinparfImportLogs\WinparfImportLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWinparfImportLogs extends ManageRecords
{
    protected static string $resource = WinparfImportLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
