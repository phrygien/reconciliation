<?php

namespace App\Filament\Resources\StockMensuels;

use App\Filament\Resources\StockMensuels\Pages\CreateStockMensuel;
use App\Filament\Resources\StockMensuels\Pages\EditStockMensuel;
use App\Filament\Resources\StockMensuels\Pages\ListStockMensuels;
use App\Filament\Resources\StockMensuels\Pages\ViewStockMensuel;
use App\Filament\Resources\StockMensuels\Schemas\StockMensuelForm;
use App\Filament\Resources\StockMensuels\Schemas\StockMensuelInfolist;
use App\Filament\Resources\StockMensuels\Tables\StockMensuelsTable;
use App\Models\StockMensuel;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StockMensuelResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'STOCK';
    protected static ?int $navigationSort = 1;

    protected static ?string $model = StockMensuel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    protected static ?string $recordTitleAttribute = 'parkod';

    public static function form(Schema $schema): Schema
    {
        return StockMensuelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockMensuelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockMensuelsTable::configure($table);
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
            'index' => ListStockMensuels::route('/'),
            'create' => CreateStockMensuel::route('/create'),
            'view' => ViewStockMensuel::route('/{record}'),
            'edit' => EditStockMensuel::route('/{record}/edit'),
        ];
    }
}
