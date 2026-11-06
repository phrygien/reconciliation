<?php

namespace App\Filament\Resources\MouvementBruts;

use App\Filament\Resources\MouvementBruts\Pages\CreateMouvementBrut;
use App\Filament\Resources\MouvementBruts\Pages\EditMouvementBrut;
use App\Filament\Resources\MouvementBruts\Pages\ListMouvementBruts;
use App\Filament\Resources\MouvementBruts\Pages\ViewMouvementBrut;
use App\Filament\Resources\MouvementBruts\Schemas\MouvementBrutForm;
use App\Filament\Resources\MouvementBruts\Schemas\MouvementBrutInfolist;
use App\Filament\Resources\MouvementBruts\Tables\MouvementBrutsTable;
use App\Models\MouvementBrut;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MouvementBrutResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'MVT';
    protected static ?int $navigationSort = 8;

    protected static ?string $model = MouvementBrut::class;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::TableDuotone;

    protected static ?string $recordTitleAttribute = 'mag_orig';

    public static function form(Schema $schema): Schema
    {
        return MouvementBrutForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MouvementBrutInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MouvementBrutsTable::configure($table);
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
            'index' => ListMouvementBruts::route('/'),
            'create' => CreateMouvementBrut::route('/create'),
            'view' => ViewMouvementBrut::route('/{record}'),
            'edit' => EditMouvementBrut::route('/{record}/edit'),
        ];
    }
}
