<?php

namespace App\Filament\Resources\MouvementBruts\RelationManagers;

use App\Models\MouvementAffecte;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffectationsRelationManager extends RelationManager
{
    protected static string $relationship = 'affectations';

    protected static ?string $title = 'Affectations';

    protected static ?string $modelLabel = 'Affectation';

    protected static ?string $pluralModelLabel = 'Affectations';

    protected static ?string $recordTitleAttribute = 'mode_affect';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('mag_orig')
                ->label('Mag. origine')
                ->maxLength(255),
            TextInput::make('mag_dest')
                ->label('Mag. destination')
                ->maxLength(255),
            TextInput::make('code_stock')
                ->label('Code stock')
                ->required()
                ->maxLength(255),
            TextInput::make('qte')
                ->label('Quantité')
                ->numeric()
                ->integer()
                ->required(),
            TextInput::make('valeur')
                ->label('Valeur')
                ->numeric()
                ->step(0.0001)
                ->required(),
            Select::make('mode_affect')
                ->label('Mode')
                ->options(array_combine(MouvementAffecte::MODES, MouvementAffecte::MODES))
                ->required(),
            DatePicker::make('date_comptable')
                ->label('Date comptable')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('mode_affect')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('mode_affect')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MouvementAffecte::MODE_AUTO => 'success',
                        MouvementAffecte::MODE_REPARTI => 'info',
                        MouvementAffecte::MODE_MANUEL => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sens')
                    ->label('Sens')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'IN' ? 'success' : 'danger'),
                TextColumn::make('mag_orig')
                    ->label('Mag. origine')
                    ->searchable(),
                TextColumn::make('mag_dest')
                    ->label('Mag. destination')
                    ->searchable(),
                TextColumn::make('code_stock')
                    ->label('Code stock')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qte')
                    ->label('Quantité')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('valeur')
                    ->label('Valeur')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('date_comptable')
                    ->label('Date comptable')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('date_comptable', 'desc')
            ->filters([
                SelectFilter::make('mode_affect')
                    ->label('Mode')
                    ->options(array_combine(MouvementAffecte::MODES, MouvementAffecte::MODES)),
            ])
            ->headerActions([
                CreateAction::make(),
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
}
