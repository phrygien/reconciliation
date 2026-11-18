<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Exception;
use InvalidArgumentException;
use App\Models\RegularisationStock;
use App\Models\StockMensuel;
use App\Models\Facture;
use Carbon\Carbon;
use App\Services\FileProcessor;

class Regularisation
{
    public const FILTRE_ALL           = 'ALL';
    public const FILTRE_A_REGULARISER = 'A_REGULARISER'; // qte_a_facturer > 0
    public const FILTRE_ANOMALIES     = 'ANOMALIES';     // reste négatif après compensation

    public const FACTURER_A_ADV   = 'ADV';
    public const FACTURER_A_COSMA = 'COSMA';

    private const CAS_TOTAL      = 'COMPENSABLE_TOTAL';
    private const STATUT_FACTURE = 'FACTURE';

    private const STATUT_A_FACTURER = 'A_FACTURER';
    private const STATUT_ANNULE     = 'ANNULE';

    private FileProcessor $processor;

    public function __construct(FileProcessor $process)
    {
        $processor = $process;
    }

    /**
     * Liste paginée des articles à régulariser et des anomalies (période OUVERTE).
     *
     * @param  string|null $periode    null = toutes les périodes ouvertes
     * @param  string|null $facturerA  null = tous, sinon 'ADV' ou 'COSMA'
     * @param  int|null    $marqueId   null = toutes les marques
     */
    public function listeRegularisation(
        int $page = 1,
        int $perPage = 50,
        string $filtre = self::FILTRE_ALL,
        ?string $periode = null,
        ?string $facturerA = null,
        ?int $marqueId = null,
    ): LengthAwarePaginator {
        try {
            return $this->requete($filtre, $periode, $facturerA, $marqueId)
                ->paginate($perPage, ['*'], 'page', $page);
        } catch (Exception $e) {
            Log::error('listeRegularisation : ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Toutes les lignes (sans pagination), lues en flux, pour l'export.
     */
    public function exportRegularisation(
        string $filtre = self::FILTRE_ALL,
        ?string $periode = null,
        ?string $facturerA = null,
        ?int $marqueId = null,
    ): LazyCollection {
        try {
            return $this->requete($filtre, $periode, $facturerA, $marqueId)->cursor();
        } catch (Exception $e) {
            Log::error('exportRegularisation : ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Requête commune (liste + export).
     */
    protected function requete(
        string $filtre,
        ?string $periode,
        ?string $facturerA,
        ?int $marqueId,
    ): Builder {
        if (! in_array($filtre, [self::FILTRE_ALL, self::FILTRE_A_REGULARISER, self::FILTRE_ANOMALIES], true)) {
            throw new InvalidArgumentException("Filtre inconnu : {$filtre}");
        }

        if ($facturerA !== null && ! in_array($facturerA, [self::FACTURER_A_ADV, self::FACTURER_A_COSMA], true)) {
            throw new InvalidArgumentException("Facturer à inconnu : {$facturerA}");
        }

        // 1) une ligne par (periode, parkod) avec ADV / COSMA côte à côte
        $pivot = DB::table('stock_mensuels as sm')
            ->join('periodes as p', function ($j) {
                $j->on('p.periode', '=', 'sm.periode')->where('p.statut', 'OUVERTE');
            })
            ->when($periode, fn ($q) => $q->where('sm.periode', $periode))
            ->selectRaw("
                sm.periode,
                sm.parkod,
                SUM(CASE WHEN sm.code_stock = 'ADV'   THEN sm.qte_ouverture ELSE 0 END) AS qte_adv,
                SUM(CASE WHEN sm.code_stock = 'ADV'   THEN sm.val_ouverture ELSE 0 END) AS val_adv,
                SUM(CASE WHEN sm.code_stock = 'COSMA' THEN sm.qte_ouverture ELSE 0 END) AS qte_cosma,
                SUM(CASE WHEN sm.code_stock = 'COSMA' THEN sm.val_ouverture ELSE 0 END) AS val_cosma
            ")
            ->groupBy('sm.periode', 'sm.parkod')
            ->havingRaw('MIN(sm.qte_ouverture) < 0');

        // 2) total et quantité compensable = min(|négatif|, positif)
        $calc = DB::query()->fromSub($pivot, 'x')->selectRaw("
            x.*,
            (x.qte_adv + x.qte_cosma) AS qte_total,
            LEAST(
                ABS(LEAST(x.qte_adv, x.qte_cosma, 0)),
                GREATEST(x.qte_adv, x.qte_cosma, 0)
            ) AS qte_a_facturer
        ");

        // 3) cas, sens de facturation, PAMP, valeur à facturer, reste non compensé
        $final = DB::query()->fromSub($calc, 'c')->selectRaw("
            c.periode,
            c.parkod,
            c.qte_adv,
            c.qte_cosma,
            c.qte_total,
            CASE
                WHEN c.qte_total >= 0     THEN 'COMPENSABLE_TOTAL'
                WHEN c.qte_a_facturer > 0 THEN 'COMPENSABLE_PARTIEL'
                ELSE 'NON_COMPENSABLE'
            END AS cas,
            CASE WHEN c.qte_a_facturer = 0 THEN NULL
                WHEN c.qte_adv < 0 THEN 'ADV' ELSE 'COSMA' END AS facturer_a,
            CASE WHEN c.qte_a_facturer = 0 THEN NULL
                WHEN c.qte_adv < 0 THEN 'COSMA' ELSE 'ADV' END AS facture_par,
            c.qte_a_facturer,

            -- PAMP d'ouverture de chaque stock (visible même pour les anomalies)
            ROUND(c.val_adv   / NULLIF(c.qte_adv,   0), 4) AS pamp_adv,
            ROUND(c.val_cosma / NULLIF(c.qte_cosma, 0), 4) AS pamp_cosma,

            -- PAMP retenu pour la facture = celui du stock qui cède
            CASE WHEN c.qte_a_facturer = 0 THEN NULL
                WHEN c.qte_adv < 0 THEN ROUND(c.val_cosma / NULLIF(c.qte_cosma, 0), 4)
                ELSE                    ROUND(c.val_adv   / NULLIF(c.qte_adv,   0), 4)
            END AS pamp,

            -- valeur à facturer = qte_a_facturer x pamp
            CASE WHEN c.qte_a_facturer = 0 THEN NULL
                WHEN c.qte_adv < 0 THEN ROUND(c.qte_a_facturer * c.val_cosma / NULLIF(c.qte_cosma, 0), 2)
                ELSE                    ROUND(c.qte_a_facturer * c.val_adv   / NULLIF(c.qte_adv,   0), 2)
            END AS valeur_a_facturer,

            GREATEST(-c.qte_total, 0) AS qte_residuelle
        ");

        // 4) infos article + filtres + tri
        return DB::query()
            ->fromSub($final, 'r')
            ->leftJoin('articles as a', 'a.parkod', '=', 'r.parkod')
            ->leftJoin('marques as m', 'm.id', '=', 'a.marque_id')
            ->select([
                'r.*',
                'a.id as article_id',
                'a.ean',
                'a.description',
                'm.nom as marque',
            ])
            ->when($filtre === self::FILTRE_A_REGULARISER, fn ($q) => $q->where('r.qte_a_facturer', '>', 0))
            ->when($filtre === self::FILTRE_ANOMALIES,     fn ($q) => $q->where('r.qte_residuelle', '>', 0))
            ->when($facturerA,                             fn ($q) => $q->where('r.facturer_a', $facturerA))
            ->when($marqueId,                              fn ($q) => $q->where('a.marque_id', $marqueId))
            ->orderBy('r.periode')
            ->orderBy('r.cas')
            ->orderBy('r.parkod');
    }

    // --------------------------------------------------------- //
    //          operation regulariser les stocks                 //
    // --------------------------------------------------------- //

    /**
     * Régularise les COMPENSABLE_TOTAL des périodes OUVERTES et génère la facture interne.
     *
     * @param  string|null $periode    null = toutes les périodes ouvertes
     * @param  string|null $facturerA  null = tous, sinon 'ADV' ou 'COSMA'
     * @param  int|null    $marqueId   null = toutes les marques
     */
    public function regulariserLeStocks(
        ?string $periode = null,
        ?string $facturerA = null,
        ?int $marqueId = null,
        ?int $user_id = null,
    ): array {
        try {
            DB::beginTransaction();

            // mêmes filtres que la liste, limités aux COMPENSABLE_TOTAL
            // (get() et non cursor() : on modifie stock_mensuels pendant la boucle)
            $candidats = $this->requete(self::FILTRE_A_REGULARISER, $periode, $facturerA, $marqueId)
                ->where('r.cas', self::CAS_TOTAL)
                ->get();

            $lot   = now()->format('YmdHis'); // un lot = un jeu de factures
            $bilan = [
                'filtres'       => compact('periode', 'facturerA', 'marqueId'),
                'regularises'   => 0,
                'ignores'       => 0,
                'total_facture' => 0.0,
                'factures'      => [],
                'erreurs'       => [],
            ];

            Log::info("Régularisation : {$candidats->count()} ligne(s) COMPENSABLE_TOTAL à traiter", $bilan['filtres']);

            foreach ($candidats as $c) {
                try {
                    $reg = $this->regulariserUneLigne($c->periode, $c->parkod, $lot, $facturerA, $user_id);

                    if ($reg === null) {
                        $bilan['ignores']++;
                        continue;
                    }

                    $bilan['total_facture'] += (float) $reg->valeur;
                    $bilan['factures'][$reg->numero_facture] =
                        ($bilan['factures'][$reg->numero_facture] ?? 0) + (float) $reg->valeur;
                } catch (Exception $e) {
                    // une ligne en erreur ne bloque pas les autres
                    Log::error("Régularisation {$c->periode} / {$c->parkod} : " . $e->getMessage());
                    $bilan['erreurs'][] = "{$c->periode} / {$c->parkod} : " . $e->getMessage();
                }
            }
            $bilan['total_facture'] = round($bilan['total_facture'], 2);

            // recalculation de la facture dans la base
            $this->recalculerFacturesDuLot($lot);
            $factures = Facture::where('lot', $lot)
                ->get(['id', 'numero', 'periode', 'facturer_a', 'facture_par', 'nb_articles', 'total_qte', 'total']);

            $bilan['factures']      = $factures->toArray();
            $bilan['total_facture'] = round((float) $factures->sum('total'), 2);
            Log::info('Régularisation terminée', $bilan);

            DB::commit();
            return $bilan;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('regulariserLeStocks : ' . $e->getMessage());
            throw $e;
        }
    }

    private function regulariserUneLigne(string $periode, string $parkod, string $lot, ?string $facturerA = null, ?int $user_id = null): ?RegularisationStock
    {
        return DB::transaction(function () use ($periode, $parkod, $lot, $facturerA, $user_id) {

            // relecture avec verrou : les données ont pu changer depuis la requête
            $stocks = StockMensuel::where('periode', $periode)
                ->where('parkod', $parkod)
                ->whereIn('code_stock', ['ADV', 'COSMA'])
                ->lockForUpdate()
                ->get()
                ->keyBy('code_stock');

            if ($stocks->count() !== 2) {
                return null;
            }

            $adv   = $stocks['ADV'];
            $cosma = $stocks['COSMA'];

            // le négatif est le débiteur (il a pris), le positif est le créditeur
            if ($adv->qte_ouverture < 0 && $cosma->qte_ouverture > 0) {
                [$deb, $cre] = [$adv, $cosma];
            } elseif ($cosma->qte_ouverture < 0 && $adv->qte_ouverture > 0) {
                [$deb, $cre] = [$cosma, $adv];
            } else {
                return null;
            }

            // le débiteur relu sous verrou doit toujours correspondre au filtre demandé
            if ($facturerA !== null && $deb->code_stock !== $facturerA) {
                return null;
            }

            $qte = (int) abs($deb->qte_ouverture);

            // total seulement : le créditeur doit couvrir tout le négatif
            if ($cre->qte_ouverture < $qte) {
                return null;
            }

            // déjà régularisé
            $existe = RegularisationStock::where('periode', $periode)
                ->where('parkod', $parkod)
                ->where('code_stock_debiteur', $deb->code_stock)
                ->exists();

            if ($existe) {
                return null;
            }

            // valorisation au PAMP d'ouverture du stock qui cède (recalculation)
            $pamp   = round($cre->val_ouverture / $cre->qte_ouverture, 4);
            $valeur = round($qte * $cre->val_ouverture / $cre->qte_ouverture, 2);

            $snap = fn (StockMensuel $s) => [
                'qte_ouverture' => (int) $s->qte_ouverture,
                'val_ouverture' => (float) $s->val_ouverture,
                'qte_cloture'   => (int) $s->qte_cloture,
                'val_cloture'   => (float) $s->val_cloture,
            ];

            // même formule de clôture que le reste de l'application (qte_out est négatif)
            $maj = function (StockMensuel $s, int $dq, float $dv) {
                $s->qte_ouverture = $s->qte_ouverture + $dq;
                $s->val_ouverture = round($s->val_ouverture + $dv, 4);
                $s->qte_cloture   = $s->qte_ouverture + $s->qte_in + $s->qte_out + $s->qte_stk;
                $s->val_cloture   = round($s->val_ouverture + $s->val_in + $s->val_out + $s->val_stk, 4);
                $s->pamp_cloture  = $s->qte_cloture > 0 ? round($s->val_cloture / $s->qte_cloture, 4) : 0;
            };

            $avant = [$deb->code_stock => $snap($deb), $cre->code_stock => $snap($cre)];

            $maj($deb, +$qte, +$valeur);
            $maj($cre, -$qte, -$valeur);

            $apres = [$deb->code_stock => $snap($deb), $cre->code_stock => $snap($cre)];

            // une facture par lot, période et société débitrice (créée à la première ligne)
            $facture = Facture::firstOrCreate(
                ['lot' => $lot, 'periode' => $periode, 'facturer_a' => $deb->code_stock],
                [
                    'numero'      => sprintf('REG-%s-%s-%s', str_replace('-', '', $periode), $deb->code_stock, $lot),
                    'facture_par' => $cre->code_stock,
                    'statut'      => Facture::STATUT_BROUILLON,
                    'user_id'     => $user_id,
                ]
            );

            $reg = RegularisationStock::create([
                'periode'              => $periode,
                'periode_origine'      => Carbon::createFromFormat('!Y-m', $periode)->subMonthNoOverflow()->format('Y-m'),
                'parkod'               => $parkod,
                'code_stock_debiteur'  => $deb->code_stock,
                'code_stock_crediteur' => $cre->code_stock,
                'qte'                  => $qte,
                'pamp'                 => $pamp,
                'valeur'               => $valeur,
                'cas'                  => self::CAS_TOTAL,
                'statut'               => self::STATUT_A_FACTURER, // passera à FACTURE à l'émission du PDF
                'facture_id'           => $facture->id,
                'avant'                => $avant,
                'apres'                => $apres,
                'user_id'              => $user_id,
            ]);

            // trace sur les 2 lignes stock_mensuels
            foreach ([$deb, $cre] as $s) {
                $hist   = $s->historique_ouverture ?? [];
                $hist[] = [
                    'date'              => now()->toDateTimeString(),
                    'qte_ouverture_avant'         => $avant[$s->code_stock]['qte_ouverture'],
                    'qte_ouverture_apres'         => $apres[$s->code_stock]['qte_ouverture'],
                    'val_ouverture_avant'         => $avant[$s->code_stock]['val_ouverture'],
                    'val_ouverture_apres'         => $apres[$s->code_stock]['val_ouverture'],
                    'regularisation_id' => $reg->id,
                    'user_id'           => $user_id,
                ];

                $s->historique_ouverture = $hist;
                $s->regularisation_id    = $reg->id;
                $s->save();
            }

            // recalculation du stock mensuel de la periode corriger
            $this->processor->recalculerStockMensuel($parkod, $periode, "ADV");
            $this->processor->recalculerStockMensuel($parkod, $periode, "COSMA");

            return $reg;
        });
    }

    private function recalculerFacturesDuLot(string $lot): void
    {
        Facture::where('lot', $lot)->each(fn (Facture $f) => $this->recalculerFacture($f));
    }

    public function recalculerFacture(Facture $facture): void
    {
        $agg = RegularisationStock::where('facture_id', $facture->id)
            ->where('statut', '!=', self::STATUT_ANNULE)
            ->selectRaw('COUNT(*) AS nb, COALESCE(SUM(qte), 0) AS qte, COALESCE(SUM(valeur), 0) AS total')
            ->first();

        $facture->nb_articles = (int) $agg->nb;
        $facture->total_qte   = (int) $agg->qte;
        $facture->total       = round((float) $agg->total, 2);

        // plus aucune ligne active : la facture est annulée
        if ($facture->nb_articles === 0) {
            $facture->statut = Facture::STATUT_ANNULEE;
        }

        $facture->save();
    }

}
