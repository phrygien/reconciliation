<?php

namespace App\Filament\Resources\Societes;

use App\Filament\Resources\Societes\Pages\CreateSociete;
use App\Filament\Resources\Societes\Pages\EditSociete;
use App\Filament\Resources\Societes\Pages\ListSocietes;
use App\Filament\Resources\Societes\Pages\ViewSociete;
use App\Filament\Resources\Societes\Schemas\SocieteForm;
use App\Filament\Resources\Societes\Schemas\SocieteInfolist;
use App\Filament\Resources\Societes\Tables\SocietesTable;
use App\Models\Societe;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SocieteResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURATION';
    protected static ?int $navigationSort = 1;

    protected static ?string $model = Societe::class;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::BuildingDuotone;

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return SocieteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SocieteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocietesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocietes::route('/'),
            'create' => CreateSociete::route('/create'),
            'view' => ViewSociete::route('/{record}'),
            'edit' => EditSociete::route('/{record}/edit'),
        ];
    }
}
