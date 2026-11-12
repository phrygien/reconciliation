<?php

namespace App\Filament\Resources\MouvementAffectes;

use App\Filament\Resources\MouvementAffectes\Pages\CreateMouvementAffecte;
use App\Filament\Resources\MouvementAffectes\Pages\EditMouvementAffecte;
use App\Filament\Resources\MouvementAffectes\Pages\ListMouvementAffectes;
use App\Filament\Resources\MouvementAffectes\Pages\ViewMouvementAffecte;
use App\Filament\Resources\MouvementAffectes\RelationManagers\MouvementBrutLieRelationManager;
use App\Filament\Resources\MouvementAffectes\RelationManagers\StockMensuelsRelationManager;
use App\Filament\Resources\MouvementAffectes\Schemas\MouvementAffecteForm;
use App\Filament\Resources\MouvementAffectes\Schemas\MouvementAffecteInfolist;
use App\Filament\Resources\MouvementAffectes\Tables\MouvementAffectesTable;
use App\Models\MouvementAffecte;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MouvementAffecteResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'MVT';
    protected static ?int $navigationSort = 6;

    protected static ?string $model = MouvementAffecte::class;

    protected static ?string $navigationLabel = 'Mouvement IN OUT';

    protected static ?string $modelLabel = 'Mouvement IN OUT';

    protected static ?string $pluralModelLabel = 'Mouvement IN OUT';

    protected static string|BackedEnum|null $navigationIcon = Phosphor::ListDashes;

    protected static ?string $recordTitleAttribute = 'mode_affect';

    public static function form(Schema $schema): Schema
    {
        return MouvementAffecteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MouvementAffecteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MouvementAffectesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MouvementBrutLieRelationManager::class,
            StockMensuelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMouvementAffectes::route('/'),
            'create' => CreateMouvementAffecte::route('/create'),
            'view' => ViewMouvementAffecte::route('/{record}'),
            'edit' => EditMouvementAffecte::route('/{record}/edit'),
        ];
    }
}
