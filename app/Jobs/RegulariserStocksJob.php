<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Regularisation;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RegulariserStocksJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries   = 1;

    /**
     * @param int[]    $marqueIds
     * @param string[] $selection  clés « periode|parkod » des lignes cochées ([] = tout le périmètre filtré)
     */
    public function __construct(
        public ?string $periode,
        public ?string $facturerA,
        public array $marqueIds,
        public ?int $userId,
        public array $selection = [],
    ) {}

    public function handle(Regularisation $service): void
    {
        set_time_limit(0);

        $bilan = $service->regulariserLeStocks(
            periode: $this->periode,
            facturerA: $this->facturerA,
            marqueIds: $this->marqueIds,
            user_id: $this->userId,
            selection: $this->selection,
        );

        $user = $this->userId ? User::find($this->userId) : null;

        if (! $user) {
            return;
        }

        $nbErreurs = count($bilan['erreurs']);

        // détail des lignes ignorées : « 12 déjà régularisé, 3 période non ouverte »
        $detailRaisons = collect($bilan['raisons'])
            ->map(fn (int $n, string $raison) => $n . ' ' . (Regularisation::RAISONS_LABELS[$raison] ?? $raison))
            ->implode(', ');

        // sélection manuelle : lignes cochées qui n'étaient pas « Compensable total »
        $horsPerimetre = $bilan['hors_perimetre'] > 0
            ? " {$bilan['hors_perimetre']} ligne(s) sélectionnée(s) non compensable(s) totalement, non traitée(s)."
            : '';

        if ($bilan['regularises'] === 0) {
            $body = $bilan['candidats'] === 0
                ? 'Aucune ligne « Compensable total » trouvée'
                . ($bilan['selection'] > 0 ? ' dans la sélection.' : ' avec ces filtres.')
                : "{$bilan['candidats']} ligne(s) trouvée(s) mais aucune traitée"
                . ($detailRaisons !== '' ? " ({$detailRaisons})" : '')
                . ($nbErreurs > 0 ? " — {$nbErreurs} erreur(s), voir les logs" : '')
                . '.';

            Notification::make()
                ->title('Aucune ligne régularisée')
                ->body($body . $horsPerimetre)
                ->warning()
                ->sendToDatabase($user);

            return;
        }

        $notification = Notification::make()
            ->title('Régularisation effectuée')
            ->body(
                "{$bilan['regularises']} ligne(s) régularisée(s), "
                . count($bilan['factures']) . ' facture(s), total '
                . number_format($bilan['total_facture'], 2, ',', ' ')
                . ". {$bilan['ignores']} ignorée(s)"
                . ($detailRaisons !== '' ? " ({$detailRaisons})" : '')
                . '.'
                . $horsPerimetre
                . ($nbErreurs > 0 ? " {$nbErreurs} erreur(s), voir les logs." : '')
            );

        $nbErreurs > 0 ? $notification->warning() : $notification->success();
        $notification->sendToDatabase($user);
    }

    public function failed(Throwable $e): void
    {
        $user = $this->userId ? User::find($this->userId) : null;

        if ($user) {
            Notification::make()
                ->title('Échec de la régularisation')
                ->body($e->getMessage())
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
