<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ImportBatch;
use App\Models\Marque;
use App\Models\Magasin;
use App\Models\Societe;
use App\Models\Periode;
use App\Models\StockMensuel;
use App\Models\MouvementAffecte;
use App\Models\MouvementBrut;
use App\Models\TypeMouvement;
use App\Models\EtatStockWinparf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;

class FileProcessor
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    // --------------------- //

    private function findOrCreateStockMensuel(string $parkod, string $annee_mois, string $code_stock): StockMensuel {
        try{

            // chercher une periode ouvert (OBLIGE D'AVOIR UNE SEULE PERIODE OUVERT )
            $periode = Periode::where('statut', 'OUVERTE')->first();

            if($periode->periode != $annee_mois){
                // verification de la date du mouvement si superieur a la periode OUVERTE
                if($periode->periode < $annee_mois){
                    // cloture la periode
                    $periode->statut = "CLOTUREE";
                    $periode->cloture_le = now();
                    $periode->save();
                    // calculation du stock cloturee
                    $this->recalculerStockMensuel($parkod, $periode->periode, $code_stock);

                    // avoir les stock d'ouverture de la nouvel periode
                    $ancien_stock_mensuel = StockMensuel::where(['parkod' => $parkod, 'periode' => $periode->periode, 'code_stock' => $code_stock])->first();
                    $qte_stock_cloture_N_min_1 = $ancien_stock_mensuel->qte_cloture;
                    $val_stock_cloture_N_min_1 = $ancien_stock_mensuel->val_cloture;

                    // creation nouveau periode
                    $new_periode = Periode::create([
                        'periode' => $annee_mois,
                        'statut' => "OUVERTE"
                    ]);

                    // creation du nouveau stock mensuel
                    $stock = StockMensuel::firstOrCreate(
                        ['parkod' => $parkod, 'periode' => $new_periode->periode, 'code_stock' => $code_stock],          // critères de recherche
                        [   'periode' => $new_periode->periode,
                            'code_stock' => $code_stock,
                            'parkod' => $parkod,
                            'qte_ouverture' => $qte_stock_cloture_N_min_1,
                            'val_ouverture' => $val_stock_cloture_N_min_1,
                            'qte_in'  => 0,
                            'val_in'  => 0,
                            'qte_out' => 0,
                            'val_out' => 0,
                            'qte_stk' => 0,
                            'val_stk' => 0,
                            'qte_cloture' => $qte_stock_cloture_N_min_1,
                            'val_cloture' => $val_stock_cloture_N_min_1,
                            'pamp_cloture' => 0
                        ] // valeurs ajoutées seulement à la création
                    );

                    return $stock;

                }else{
                    throw new Exception('La periode ouvert est superieure a la periode du mouvement en question (un mouvement anterieur)');
                }
            }

            // chercher un stock mensuel, si il n'y a pas, il faut le creer
            $stock = StockMensuel::firstOrCreate(
                ['parkod' => $parkod, 'periode' => $annee_mois, 'code_stock' => $code_stock],          // critères de recherche
                [   'periode' => $annee_mois,
                    'code_stock' => $code_stock,
                    'parkod' => $parkod,
                    'qte_ouverture' => 0,
                    'val_ouverture' => 0,
                    'qte_in'  => 0,
                    'val_in'  => 0,
                    'qte_out' => 0,
                    'val_out' => 0,
                    'qte_stk' => 0,
                    'val_stk' => 0,
                    'qte_cloture' => 0,
                    'val_cloture' => 0,
                    'pamp_cloture' => 0
                ] // valeurs ajoutées seulement à la création
            );

            // retourner le stock ouvert
            return $stock;

        }catch(Exception $e){
            throw $e;
        }
    }

    // lecture
    public function read(string $path, string $separateur = ';'): array
    {
        $fullPath = Storage::disk('local')->path($path);
        $lignes = [];

        if (($handle = fopen($fullPath, 'r')) === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier.");
        }

        $entetes = fgetcsv($handle, 0, $separateur);

        while (($row = fgetcsv($handle, 0, $separateur)) !== false) {
            $lignes[] = array_combine($entetes, $row);
        }

        fclose($handle);

        return $lignes;
    }

    // recalculation du stock mensuel
    protected function recalculerStockMensuel(string $parkod, string $periode, string $societe): void
    {
        $debutMois = $periode . '-01';
        $finMois   = date('Y-m-t', strtotime($debutMois));

        // recherche du stock de la periode donnee
        $periode_obj = Periode::where('periode', $periode)->first();
        $STOCK = StockMensuel::firstOrCreate(
            ['parkod' => $parkod, 'periode' => $periode_obj->periode, 'code_stock' => $societe],
            [   'periode' => $periode_obj->periode,
                'code_stock' => $societe,
                'parkod' => $parkod,
                'qte_ouverture' => 0,
                'val_ouverture' => 0,
                'qte_in'  => 0,
                'val_in'  => 0,
                'qte_out' => 0,
                'val_out' => 0,
                'qte_stk' => 0,
                'val_stk' => 0,
                'qte_cloture' => 0,
                'val_cloture' => 0,
                'pamp_cloture' => 0
            ] // valeurs ajoutées seulement à la création
        );

        $agg = MouvementAffecte::query()
            ->join('mouvement_bruts as b', 'b.id', '=', 'mouvement_affectes.mouvement_brut_id')
            ->where('b.parkod', $parkod)
            ->where('mouvement_affectes.code_stock', $societe)
            ->whereBetween('mouvement_affectes.date_comptable', [$debutMois, $finMois])
            ->selectRaw("
                SUM(CASE WHEN b.in_out = 'IN'  THEN mouvement_affectes.qte    ELSE 0 END) as qte_in,
                SUM(CASE WHEN b.in_out = 'IN'  THEN mouvement_affectes.valeur ELSE 0 END) as val_in,
                SUM(CASE WHEN b.in_out = 'OUT' THEN mouvement_affectes.qte    ELSE 0 END) as qte_out,
                SUM(CASE WHEN b.in_out = 'OUT' THEN mouvement_affectes.valeur ELSE 0 END) as val_out
            ")
            ->first();

        $STOCK->qte_in  = $agg->qte_in  ?? 0;
        $STOCK->val_in  = $agg->val_in  ?? 0;
        $STOCK->qte_out = $agg->qte_out ?? 0;
        $STOCK->val_out = $agg->val_out ?? 0;

        // dernier état Winparf du mois (instantané, pas un cumul)
        $stk = EtatStockWinparf::query()
            ->where('code_stock', $societe)
            ->whereBetween('date_comptable', [$debutMois, $finMois])
            ->whereHas('mouvementBrut', fn ($q) => $q->where('parkod', $parkod))
            ->orderByDesc('date_comptable')
            ->first();

        $STOCK->qte_stk = $stk->qte    ?? 0;
        $STOCK->val_stk = $stk->valeur ?? 0;

        $STOCK->qte_cloture = $STOCK->qte_ouverture + $STOCK->qte_in + $STOCK->qte_out;
        $STOCK->val_cloture = $STOCK->val_ouverture + $STOCK->val_in + $STOCK->val_out;

        if ($STOCK->qte_cloture == 0) {
            $STOCK->val_cloture = 0;
        }

        $STOCK->pamp_cloture = $STOCK->qte_cloture > 0
            ? round($STOCK->val_cloture / $STOCK->qte_cloture, 4)
            : 0;

        $STOCK->save();
    }

    // --------------------- //

    // read file mvt
    public function import_file(){
        try{

            // trie ASC par rapport au date de mouvement
            $rows = collect($this->read("/depot_winparf/received/ADV-COSMA-MVT_20261020_013122.TXT"))->sortBy([
                ['DATE_MVT', 'asc'],
                ['SEQUENCE', 'asc'],
            ])->values()->all(); 

            $touches = []; // variable de recalculation du stock mensuel

            //sauvegarde du batch (exemple)
            $BATCH = ImportBatch::create([
                'nom_fichier' => "ADV-COSMA-MVT_20261020_013122.TXT",
                'importe_le' => now(),
                'date_min' => collect($rows)->min('DATE_MVT'),
                'date_max' => collect($rows)->max('DATE_MVT'),
                'nb_ligne' => count($rows)
            ]);

            DB::beginTransaction();

            foreach($rows as $row){

                //avoir la periode
                $PERIODE_MVT = substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2);

                //creation marque | creation/modification article
                $MARQUE = Marque::firstOrCreate(
                    ["nom" => $row['MARQUE']],
                    ["nom" => $row['MARQUE']]
                );
                $ARTICLE = Article::updateOrCreate(
                    ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id],
                    ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id, 'description' => $row['DESCR_ARTICLE'], 'paht' => $row['PAHT']]
                );

                //avoir stock
                $STOCK = $this->findOrCreateStockMensuel($row['PARKOD'], $PERIODE_MVT, $row['SOCIETE']);

                //avoir type mouvement
                $categorie = "";
                if($row['IN_OUT'] == "OUT"){
                    $categorie = "SELL_OUT";
                }
                if($row['IN_OUT'] == "IN"){
                    $categorie = "SELL_IN";
                }
                if($row['IN_OUT'] == "STK"){
                    $categorie = "INVENTAIRE";
                }
                $MVT_TYPE = TypeMouvement::firstOrCreate(
                    ["type_mouvement" => $row['TYP_MVT'], "sens" => $row['IN_OUT'], "categorie" => $categorie], // critères de recherche
                    ["type_mouvement" => $row['TYP_MVT'], "sens" => $row['IN_OUT'], "categorie" => $categorie] // valeurs ajoutées seulement à la création
                );

                //Stocker le mouvement BRUT
                $hash = hash('sha256', implode('|', [
                    trim($row['SEQUENCE']),
                    trim($row['IN_OUT']),
                    trim($row['SOCIETE']),
                    trim($row['DATE_MVT']),
                    trim($row['TYP_MVT']),
                    trim($row['MAG_ORIG']),
                    trim($row['MAG_DEST']),
                    trim($row['PARKOD']),
                    number_format((float) $row['QTE'], 3, '.', ''),
                    number_format((float) $row['MONTANT_IN_OUT'], 2, '.', ''),
                    trim($row['ID_TRANSAC']),
                ]));
                $MVT_BRUT = MouvementBrut::updateOrCreate(
                    ['hash_ligne' => $hash],          // critère d'unicité
                    [
                        'batch_id' => $BATCH->id,
                        'sequence_src'  => $row['SEQUENCE'],
                        'in_out' => $row['IN_OUT'],
                        'societe_src' => $row['SOCIETE'],
                        'date_mvt' => $row['DATE_MVT'],
                        'typ_mvt' => $row['TYP_MVT'],
                        'mag_orig' => $row['MAG_ORIG'],
                        'mag_dest' => $row['MAG_DEST'],
                        'parkod' => $row['PARKOD'],
                        'qte' => $row['QTE'],
                        'montant' => $row['MONTANT_IN_OUT'],
                        'paht' => $row['PAHT'],
                        'pamp' => $row['PAMP'],
                        'punet' => $row['PUNET'],
                        'id_transac' => $row['ID_TRANSAC'],
                        'hash_ligne' => $hash
                    ]
                );

                //Mouvement d'affectation ou de isolation de stock
                if($row['IN_OUT'] == "IN" || $row['IN_OUT'] == "OUT"){
                    $MVT_AF = MouvementAffecte::updateOrCreate(
                        ['mouvement_brut_id' =>  $MVT_BRUT->id],
                        [
                            'mouvement_brut_id' => $MVT_BRUT->id,
                            'mag_orig' => $row['MAG_ORIG'],
                            'mag_dest' => $row['MAG_DEST'],
                            'code_stock' => $row['SOCIETE'],
                            'qte' => $row['QTE'],
                            'valeur' => $row['MONTANT_IN_OUT'],
                            'mode_affect' => MouvementAffecte::MODE_AUTO,
                            'date_comptable' => substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2) . "-" . substr($row['DATE_MVT'], 6, 2)
                        ]
                    );
                    // mise a jour du stock mensuel
                    if($row['IN_OUT'] == "IN"){
                        $STOCK->qte_in += $row['QTE'];
                        $STOCK->val_in += $row['MONTANT_IN_OUT'];
                    }else{
                        $STOCK->qte_out += $row['QTE'];
                        $STOCK->val_out += $row['MONTANT_IN_OUT'];
                    }
                }
                if($row['IN_OUT'] == "STK"){
                    $INV_WP = EtatStockWinparf::updateOrCreate(
                        ['mouvement_brut_id' =>  $MVT_BRUT->id],
                        [
                            'mouvement_brut_id' => $MVT_BRUT->id,
                            'code_stock' => $row['SOCIETE'],
                            'qte' => $row['QTE'],
                            'valeur' => $row['MONTANT_IN_OUT'],
                            'date_comptable' => substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2) . "-" . substr($row['DATE_MVT'], 6, 2)
                        ]
                    );
                    //mise a jour du stock mensuel (invetaire fixe sur winparf)
                    $STOCK->qte_stk = $row['QTE'];
                    $STOCK->val_stk = $row['MONTANT_IN_OUT'];
                }

                //calcule du stock de cloture
                $STOCK->qte_cloture = $STOCK->qte_ouverture + $STOCK->qte_in + $STOCK->qte_out;
                $STOCK->val_cloture = $STOCK->val_ouverture + $STOCK->val_in + $STOCK->val_out;
                $STOCK->pamp_cloture = $STOCK->qte_cloture > 0 ? round($STOCK->val_cloture / $STOCK->qte_cloture, 4) : 0;
                $STOCK->save();

                // mettre en touche pour recalculer le stock mensuel (dual verification/calculation)
                $touches[$row['PARKOD'] . '|' . $PERIODE_MVT . '|' . $row['SOCIETE']] = [
                    'parkod'  => $row['PARKOD'],
                    'periode' => $PERIODE_MVT,
                    'societe' => $row['SOCIETE'],
                ];
            }

            // recalculation du stock mensuel
            foreach ($touches as $t) {
                $this->recalculerStockMensuel($t['parkod'], $t['periode'], $t['societe']);
            }

            // mettre a jour le statut du batch
            $BATCH->statut = "SUCCESS";
            $BATCH->save();

            DB::commit();

            return true;

        }catch(Exception $e){
            DB::rollBack();
            //mettre failed l'importation
            $BATCH->statut = "FAILED";
            $BATCH->save();

            throw $e;
        }
    }


}
