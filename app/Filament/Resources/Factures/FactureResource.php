<?php

namespace App\Filament\Resources\Factures;

use App\Filament\Resources\Factures\Pages\CreateFacture;
use App\Filament\Resources\Factures\Pages\EditFacture;
use App\Filament\Resources\Factures\Pages\ListFactures;
use App\Filament\Resources\Factures\Pages\ViewFacture;
use App\Filament\Resources\Factures\RelationManagers\LignesRelationManager;
use App\Filament\Resources\Factures\Schemas\FactureForm;
use App\Filament\Resources\Factures\Schemas\FactureInfolist;
use App\Filament\Resources\Factures\Tables\FacturesTable;
use App\Models\Facture;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FactureResource extends Resource
{
    protected static ?string $model = Facture::class;

    protected static ?string $navigationLabel = 'Factures';
    protected static string | UnitEnum | null $navigationGroup = 'Régularisation';

    protected static string|BackedEnum|null $navigationIcon = Phosphor::Invoice;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'numero';

    public static function form(Schema $schema): Schema
    {
        return FactureForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FactureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacturesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LignesRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFactures::route('/'),
            'create' => CreateFacture::route('/create'),
            'view' => ViewFacture::route('/{record}'),
            'edit' => EditFacture::route('/{record}/edit'),
        ];
    }
}
