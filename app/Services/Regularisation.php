<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Exception;
use InvalidArgumentException;

class Regularisation
{
    public const FILTRE_ALL           = 'ALL';
    public const FILTRE_A_REGULARISER = 'A_REGULARISER'; // qte_a_facturer > 0
    public const FILTRE_ANOMALIES     = 'ANOMALIES';     // reste négatif après compensation

    public const FACTURER_A_ADV   = 'ADV';
    public const FACTURER_A_COSMA = 'COSMA';

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
}
