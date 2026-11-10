<?php

namespace App\Filament\Resources\Periodes;

use App\Filament\Resources\Periodes\Pages\CreatePeriode;
use App\Filament\Resources\Periodes\Pages\EditPeriode;
use App\Filament\Resources\Periodes\Pages\ListPeriodes;
use App\Filament\Resources\Periodes\Pages\ViewPeriode;
use App\Filament\Resources\Periodes\Schemas\PeriodeForm;
use App\Filament\Resources\Periodes\Schemas\PeriodeInfolist;
use App\Filament\Resources\Periodes\Tables\PeriodesTable;
use App\Models\Periode;
use BackedEnum;
use UnitEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PeriodeResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURATION';

    protected static ?string $model = Periode::class;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::CalendarDots;

    protected static ?string $recordTitleAttribute = 'periode';

    public static function form(Schema $schema): Schema
    {
        return PeriodeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PeriodeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PeriodesTable::configure($table);
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
            'index' => ListPeriodes::route('/'),
            'create' => CreatePeriode::route('/create'),
            'view' => ViewPeriode::route('/{record}'),
            'edit' => EditPeriode::route('/{record}/edit'),
        ];
    }
}
