<?php

namespace App\Filament\Resources\EtatStockWinparves;

use App\Filament\Resources\EtatStockWinparves\Pages\CreateEtatStockWinparf;
use App\Filament\Resources\EtatStockWinparves\Pages\EditEtatStockWinparf;
use App\Filament\Resources\EtatStockWinparves\Pages\ListEtatStockWinparves;
use App\Filament\Resources\EtatStockWinparves\Schemas\EtatStockWinparfForm;
use App\Filament\Resources\EtatStockWinparves\Tables\EtatStockWinparvesTable;
use App\Models\EtatStockWinparf;
use BackedEnum;
use UnitEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EtatStockWinparfResource extends Resource
{
    protected static ?string $model = EtatStockWinparf::class;

    protected static string | UnitEnum | null $navigationGroup = 'MVT';

    protected static ?string $navigationLabel = 'Ecart Inventaire';

    protected static ?string $modelLabel = 'Ecart Inventaire';

    protected static ?string $pluralModelLabel = 'Ecart Inventaire';
    protected static ?int $navigationSort = 7;
    protected static string|BackedEnum|null $navigationIcon = Phosphor::CalculatorThin;

    protected static ?string $recordTitleAttribute = 'code_stock';

    public static function form(Schema $schema): Schema
    {
        return EtatStockWinparfForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EtatStockWinparvesTable::configure($table);
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
            'index' => ListEtatStockWinparves::route('/'),
            'create' => CreateEtatStockWinparf::route('/create'),
            'edit' => EditEtatStockWinparf::route('/{record}/edit'),
        ];
    }
}
