<?php

namespace App\Filament\Resources\Marques;

use App\Filament\Resources\Marques\Pages\CreateMarque;
use App\Filament\Resources\Marques\Pages\EditMarque;
use App\Filament\Resources\Marques\Pages\ListMarques;
use App\Filament\Resources\Marques\Pages\ViewMarque;
use App\Filament\Resources\Marques\Schemas\MarqueForm;
use App\Filament\Resources\Marques\Schemas\MarqueInfolist;
use App\Filament\Resources\Marques\Tables\MarquesTable;
use App\Models\Marque;
use BackedEnum;
use UnitEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MarqueResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURATION';
    protected static ?int $navigationSort = 4;

    protected static ?string $model = Marque::class;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::TagDuotone;

    protected static ?string $recordTitleAttribute = 'non';

    public static function form(Schema $schema): Schema
    {
        return MarqueForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MarqueInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarquesTable::configure($table);
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
            'index' => ListMarques::route('/'),
            'create' => CreateMarque::route('/create'),
            'view' => ViewMarque::route('/{record}'),
            'edit' => EditMarque::route('/{record}/edit'),
        ];
    }
}
