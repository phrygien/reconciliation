<?php

namespace App\Filament\Pages;

use App\Filament\Exports\MouvementBrutExporter;
use App\Models\Magasin;
use App\Models\MouvementBrut;
use App\Models\Societe;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

class MouvementsParMagasin extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string | UnitEnum | null $navigationGroup = 'MVT';

    protected static ?string $navigationLabel = 'Mouvements par magasin';

    protected static ?string $title = 'Mouvements par magasin';

    protected static ?string $slug = 'mouvements-par-magasin';

    protected string $view = 'filament.pages.mouvements-par-magasin';

    /** ID de la société active. */
    #[Url(as: 'societe')]
    public string $societeTab = '';

    /** Code (mag_code) du magasin actif. */
    #[Url(as: 'magasin')]
    public string $activeTab = '';

    public function mount(): void
    {
        if ($this->societeTab === '' || ! $this->societes->contains('id', (int) $this->societeTab)) {
            $this->societeTab = (string) ($this->societes->first()?->id ?? '');
        }

        unset($this->magasins);

        if ($this->activeTab === '' || ! $this->magasins->contains('mag_code', $this->activeTab)) {
            $this->activeTab = (string) ($this->magasins->first()?->mag_code ?? '');
        }
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    #[Computed]
    public function societes(): Collection
    {
        return Societe::query()->orderBy('code_stock')->get();
    }

    /** Magasins de la société active (hors dépôts). */
    #[Computed]
    public function magasins(): Collection
    {
        return Magasin::query()
            ->where('societe_id', $this->societeTab)
            ->where('type_magasin', '!=', 'DEPOT')
            ->orderBy('mag_code')
            ->get();
    }

    public function selectSociete(string $id): void
    {
        $this->societeTab = $id;

        unset($this->magasins);

        $this->activeTab = (string) ($this->magasins->first()?->mag_code ?? '');

        $this->resetPage($this->getTablePaginationPageName());
    }

    public function selectMagasin(string $code): void
    {
        $this->activeTab = $code;

        $this->resetPage($this->getTablePaginationPageName());
    }

    /** OUT du magasin actif : mag_dest = magasin et in_out = OUT. */
    protected function baseQuery(): Builder
    {
        return MouvementBrut::query()
            ->duMagasin($this->activeTab)
            ->out();
    }

    /** Applique une période (YYYY-MM) sur date_mvt. */
    protected static function appliquerPeriode(Builder $query, ?string $periode): Builder
    {
        if (! $periode) {
            return $query;
        }

        $debut = Carbon::createFromFormat('Y-m-d', $periode . '-01')->startOfMonth();

        return $query->whereBetween('date_mvt', [$debut, $debut->copy()->endOfMonth()]);
    }

    /**
     * Requête d'export : uniquement le magasin demandé (onglet actif) + période choisie.
     */
    protected static function exportQuery(?string $magasin, ?string $periode): Builder
    {
        $query = MouvementBrut::query();

        // Sécurité : aucun magasin => aucun résultat (jamais "tous les magasins")
        if (blank($magasin)) {
            return $query->whereRaw('1 = 0');
        }

        $query->duMagasin($magasin)->out();

        return static::appliquerPeriode($query, $periode)
            ->with(['magasinOrigine', 'magasin', 'article'])
            ->orderByDesc('date_mvt');
    }

    #[Computed]
    public function totaux(): array
    {
        $row = $this->getFilteredTableQuery()
            ->reorder()
            ->selectRaw('
                COUNT(*) AS nb,
                COALESCE(SUM(qte), 0) AS qte_out,
                COALESCE(SUM(montant), 0) AS montant_out
            ')
            ->toBase()
            ->first();

        return [
            'nb'          => (int) $row->nb,
            'qte_out'     => (int) $row->qte_out,
            'montant_out' => (float) $row->montant_out,
        ];
    }

    /**
     * Périodes (YYYY-MM) disponibles pour le magasin actif.
     */
    public function periodes(): array
    {
        return $this->baseQuery()
            ->reorder()
            ->selectRaw("DISTINCT DATE_FORMAT(date_mvt, '%Y-%m') AS periode")
            ->orderByDesc('periode')
            ->pluck('periode', 'periode')
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery()->with(['magasinOrigine', 'magasin', 'article']))
            ->headerActions([
                ExportAction::make()
                    ->label('Exporter Excel')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->exporter(MouvementBrutExporter::class)
                    ->formats([ExportFormat::Xlsx])
                    ->columnMapping(false)
                    ->modalHeading('Exporter les mouvements OUT')
                    ->modalDescription(function (MouvementsParMagasin $livewire): string {
                        $mag = $livewire->magasins->firstWhere('mag_code', $livewire->activeTab);

                        return 'Magasin : ' . $livewire->activeTab . ($mag?->libelle ? ' · ' . $mag->libelle : '');
                    })
                    ->schema(fn (MouvementsParMagasin $livewire): array => [
                        Select::make('periode')
                            ->label('Période')
                            ->placeholder('Toutes les périodes')
                            ->options(fn (): array => $livewire->periodes())
                            ->default($livewire->tableFilters['periode']['value'] ?? null)
                            ->native(false)
                            ->searchable(),
                    ])
                    ->options(fn (MouvementsParMagasin $livewire): array => [
                        'magasin' => $livewire->activeTab,
                    ])
                    ->modifyQueryUsing(fn (Builder $query, array $options): Builder => static::exportQuery(
                        $options['magasin'] ?? null,
                        $options['periode'] ?? null,
                    )),
            ])
            ->columns([
                TextColumn::make('date_mvt')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('in_out')
                    ->label('IN/OUT')
                    ->badge()
                    ->color(fn (?string $state): string => str_starts_with(strtoupper((string) $state), 'I') ? 'success' : 'danger')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('typ_mvt')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('mag_orig')
                    ->label('Origine')
                    ->description(fn (MouvementBrut $record): ?string => $record->magasinOrigine?->libelle)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('mag_dest')
                    ->label('Magasin')
                    ->description(fn (MouvementBrut $record): ?string => $record->magasin?->libelle)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('parkod')
                    ->label('Article')
                    ->description(fn (MouvementBrut $record): ?string => $record->article?->description)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('ean')
                    ->label('EAN')
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('qte')
                    ->label('Qté')
                    ->numeric(thousandsSeparator: ' ')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('montant')
                    ->label('Montant')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: ' ')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Période')
                    ->placeholder('Toutes les périodes')
                    ->options(fn (): array => $this->periodes())
                    ->query(fn (Builder $query, array $data): Builder => static::appliquerPeriode($query, $data['value'] ?? null)),
            ])
            ->defaultSort('date_mvt', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Aucun mouvement OUT pour ce magasin');
    }
}
