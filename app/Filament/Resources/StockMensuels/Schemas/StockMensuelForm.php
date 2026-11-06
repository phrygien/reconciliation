<?php

namespace App\Filament\Resources\StockMensuels\Schemas;

use App\Models\Article;
use App\Models\Periode;
use App\Models\Societe;
use App\Models\StockMensuel;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class StockMensuelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Wizard::make([
                    Step::make('Identification')
                        ->description('Période, société et article')
                        ->columns(2)
                        ->schema([
                            Select::make('periode')
                                ->label('Période')
                                ->options(fn (?StockMensuel $record) => Periode::query()
                                    ->when(! $record, fn ($q) => $q->ouvertes())
                                    ->orderByDesc('periode')
                                    ->pluck('periode', 'periode')
                                    ->all())
                                ->default(fn () => Periode::ouvertes()->orderByDesc('periode')->value('periode'))
                                ->searchable()
                                ->required()
                                ->disabled(fn (?StockMensuel $record) => $record !== null)
                                ->dehydrated()
                                ->helperText('Seules les périodes ouvertes sont proposées.'),

                            Select::make('code_stock')
                                ->label('Société')
                                ->options(fn () => Societe::query()
                                    ->orderBy('libelle')
                                    ->pluck('libelle', 'code_stock')
                                    ->all())
                                ->searchable()
                                ->required()
                                ->disabled(fn (?StockMensuel $record) => $record !== null)
                                ->dehydrated(),

                            Select::make('parkod')
                                ->label('Article')
                                ->searchable()
                                ->required()
                                ->columnSpanFull()
                                ->disabled(fn (?StockMensuel $record) => $record !== null)
                                ->dehydrated()
                                ->getSearchResultsUsing(fn (string $search) => Article::query()
                                    ->where(fn ($q) => $q
                                        ->where('parkod', 'like', "%{$search}%")
                                        ->orWhere('description', 'like', "%{$search}%"))
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Article $a) => [$a->parkod => "{$a->parkod} - {$a->description}"])
                                    ->all())
                                ->getOptionLabelUsing(function ($value) {
                                    $a = Article::where('parkod', $value)->first();

                                    return $a ? "{$a->parkod} - {$a->description}" : $value;
                                })
                                ->unique(
                                    table: 'stock_mensuels',
                                    column: 'parkod',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                        ->where('periode', $get('periode'))
                                        ->where('code_stock', $get('code_stock')),
                                )
                                ->validationMessages([
                                    'unique' => 'Cet article existe déjà pour cette période et cette société.',
                                ]),
                        ]),

                    Step::make('Ouverture')
                        ->description('Stock de départ')
                        ->columns(2)
                        ->schema([
                            TextInput::make('qte_ouverture')
                                ->label('Quantité')
                                ->required()->numeric()->default(0)->live(onBlur: true),
                            TextInput::make('val_ouverture')
                                ->label('Valeur')
                                ->required()->numeric()->step(0.0001)->default(0)->live(onBlur: true),
                        ]),

                    Step::make('Mouvements')
                        ->description('Entrées, sorties et écart')
                        ->columns(2)
                        ->schema([
                            TextInput::make('qte_in')
                                ->label('Entrées - quantité')
                                ->required()->numeric()->default(0)->live(onBlur: true),
                            TextInput::make('val_in')
                                ->label('Entrées - valeur')
                                ->required()->numeric()->step(0.0001)->default(0)->live(onBlur: true),

                            TextInput::make('qte_out')
                                ->label('Sorties - quantité')
                                ->required()->numeric()->default(0)->live(onBlur: true)
                                ->helperText('Saisir en valeur négative (ex. -5).'),
                            TextInput::make('val_out')
                                ->label('Sorties - valeur')
                                ->required()->numeric()->step(0.0001)->default(0)->live(onBlur: true)
                                ->helperText('Saisir en valeur négative.'),

                            TextInput::make('qte_stk')
                                ->label('Écart inventaire - quantité')
                                ->required()->numeric()->default(0)->live(onBlur: true),
                            TextInput::make('val_stk')
                                ->label('Écart inventaire - valeur')
                                ->required()->numeric()->step(0.0001)->default(0)->live(onBlur: true),
                        ]),

                    Step::make('Clôture')
                        ->description('Stock final et PAMP')
                        ->columns(3)
                        ->schema([
                            TextInput::make('qte_cloture')
                                ->label('Quantité')
                                ->required()->numeric()->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculerPamp($get, $set))
                                ->suffixAction(
                                    Action::make('qteTheorique')
                                        ->label('Calculer')
                                        ->action(function (Get $get, Set $set) {
                                            $set('qte_cloture', (int) $get('qte_ouverture')
                                                + (int) $get('qte_in')
                                                + (int) $get('qte_out')
                                                + (int) $get('qte_stk'));
                                            self::recalculerPamp($get, $set);
                                        })
                                ),

                            TextInput::make('val_cloture')
                                ->label('Valeur')
                                ->required()->numeric()->step(0.0001)->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculerPamp($get, $set))
                                ->suffixAction(
                                    Action::make('valTheorique')
                                        ->label('Calculer')
                                        ->action(function (Get $get, Set $set) {
                                            $set('val_cloture', round(
                                                (float) $get('val_ouverture')
                                                + (float) $get('val_in')
                                                + (float) $get('val_out')
                                                + (float) $get('val_stk'),
                                                4
                                            ));
                                            self::recalculerPamp($get, $set);
                                        })
                                ),

                            TextInput::make('pamp_cloture')
                                ->label('PAMP')
                                ->required()->numeric()->step(0.0001)->default(0),
                        ]),

                    Step::make('Contrôle')
                        ->description('Vérification avant enregistrement')
                        ->schema([
                            TextInput::make('controle')
                                ->label('Cohérence')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('-')
                                ->formatStateUsing(fn () => null)
                                ->live()
                                ->afterStateHydrated(fn (Set $set, Get $get) => $set('controle', self::controle($get)))
                                ->helperText('Clôture = ouverture + entrées + sorties + écart. Les sorties sont négatives.'),
                        ])
                        ->afterValidation(fn (Get $get, Set $set) => $set('controle', self::controle($get))),
                ])
                    ->columnSpanFull()
                    ->skippable(fn (?StockMensuel $record) => $record !== null)
                    ->persistStepInQueryString(),
            ]);
    }

    private static function recalculerPamp(Get $get, Set $set): void
    {
        $qte = (float) $get('qte_cloture');

        $set('pamp_cloture', $qte > 0
            ? round((float) $get('val_cloture') / $qte, 4)
            : 0);
    }

    private static function controle(Get $get): string
    {
        $qteTheo = (int) $get('qte_ouverture') + (int) $get('qte_in') + (int) $get('qte_out') + (int) $get('qte_stk');
        $valTheo = round((float) $get('val_ouverture') + (float) $get('val_in') + (float) $get('val_out') + (float) $get('val_stk'), 4);

        $ok = (int) $get('qte_cloture') === $qteTheo
            && abs((float) $get('val_cloture') - $valTheo) < 0.00005;

        return $ok
            ? 'Conforme'
            : 'Écart : théorique ' . number_format($qteTheo, 0, ',', ' ') . ' u. / ' . number_format($valTheo, 2, ',', ' ');
    }
}
