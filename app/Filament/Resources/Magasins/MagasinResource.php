<?php

namespace App\Filament\Resources\Magasins;

use App\Filament\Resources\Magasins\Pages\ManageMagasins;
use App\Models\Magasin;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MagasinResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURATION';
    protected static ?int $navigationSort = 2;

    protected static ?string $model = Magasin::class;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::StorefrontDuotone;

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mag_code')
                    ->required(),
                TextInput::make('libelle')
                    ->required(),
                Select::make('societe_id')
                    ->relationship('societe', 'libelle')
                    ->required(),
                Select::make('type_magasin')
                    ->options([
            'DEPOT' => 'D e p o t',
            'PHYSIQUE' => 'P h y s i q u e',
            'WEB' => 'W e b',
            'SURSTOCK' => 'S u r s t o c k',
        ])
                    ->required(),
                Toggle::make('est_web')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('libelle')
            ->columns([
                TextColumn::make('mag_code')
                    ->searchable(),
                TextColumn::make('libelle')
                    ->searchable(),
                TextColumn::make('societe.libelle')
                    ->searchable(),
                TextColumn::make('type_magasin')
                    ->badge(),
                IconColumn::make('est_web')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMagasins::route('/'),
        ];
    }
}
