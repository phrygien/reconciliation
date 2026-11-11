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
use Illuminate\Support\Facades\Log;
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

    private function findOrCreateStockMensuel(string $parkod, string $annee_mois, string $code_stock): StockMensuel
    {
        $periode = Periode::where('statut', 'OUVERTE')->firstOrFail();

        if ($periode->periode > $annee_mois) {
            throw new Exception('La période ouverte est supérieure à la période du mouvement (mouvement antérieur)');
        }

        if ($periode->periode < $annee_mois) {
            Log::channel('stderr')->info($periode->periode . " < " . $annee_mois); 
            $this->cloturerPeriode($periode);
            $this->ouvrirPeriode($annee_mois, $periode->periode);
        }

        //---------------------------//
        //          SIBLINGS         //
        //---------------------------// 

        //preparation stock ouverture sibblings
        $code_stock_siblings =  ($code_stock == "ADV") ? "COSMA" : "ADV";

        // dernier stock connu avant cette période (même parkod + même société)
        $prev_siblings = StockMensuel::where('parkod', $parkod)
            ->where('code_stock', $code_stock_siblings)
            ->where('periode', '<', $annee_mois)
            ->orderByDesc('periode')
            ->first();

        $qteOuv_siblings = $prev_siblings->qte_cloture ?? 0;
        $valOuv_siblings = $prev_siblings->val_cloture ?? 0;

        // creation du stock virtuel soeur du code stock (ADV / COSMA)  
        StockMensuel::firstOrCreate(
            ['parkod' => $parkod, 'periode' => $annee_mois, 'code_stock' => $code_stock_siblings],
            [
                'qte_ouverture' => $qteOuv_siblings,
                'val_ouverture' => $valOuv_siblings,
                'qte_in' => 0, 'val_in' => 0,
                'qte_out' => 0, 'val_out' => 0,
                'qte_stk' => 0, 'val_stk' => 0,
                'qte_cloture' => $qteOuv_siblings,
                'val_cloture' => $valOuv_siblings,
                'pamp_cloture' => $qteOuv_siblings > 0 ? round($valOuv_siblings / $qteOuv_siblings, 4) : 0,
            ]
        );

        //---------------------------//
        //          ME               //
        //---------------------------// 

        // dernier stock connu avant cette période (même parkod + même société)
        $prev = StockMensuel::where('parkod', $parkod)
            ->where('code_stock', $code_stock)
            ->where('periode', '<', $annee_mois)
            ->orderByDesc('periode')
            ->first();

        $qteOuv = $prev->qte_cloture ?? 0;
        $valOuv = $prev->val_cloture ?? 0;

        return StockMensuel::firstOrCreate(
            ['parkod' => $parkod, 'periode' => $annee_mois, 'code_stock' => $code_stock],
            [
                'qte_ouverture' => $qteOuv,
                'val_ouverture' => $valOuv,
                'qte_in' => 0, 'val_in' => 0,
                'qte_out' => 0, 'val_out' => 0,
                'qte_stk' => 0, 'val_stk' => 0,
                'qte_cloture' => $qteOuv,
                'val_cloture' => $valOuv,
                'pamp_cloture' => $qteOuv > 0 ? round($valOuv / $qteOuv, 4) : 0,
            ]
        );
    }

    private function cloturerPeriode(Periode $periode): void
    {
        Log::channel('stderr')->info("initatialization de la cloturation de la periode : " . $periode->periode . " (recalculation de tout le stock mensuel)");

        // recalcule TOUS les stocks de la période, pas seulement celui de la ligne en cours
        StockMensuel::where('periode', $periode->periode)
            ->get(['parkod', 'code_stock'])
            ->each(fn ($s) => $this->recalculerStockMensuel($s->parkod, $periode->periode, $s->code_stock));

        Log::channel('stderr')->info("Fin de la cloturation de la periode : " . $periode->periode);

        $periode->statut = 'CLOTUREE';
        $periode->cloture_le = now();
        $periode->save();
    }

    private function ouvrirPeriode(string $new_periode, string $prev_periode): void
    {
        Log::channel('stderr')->info("initialisation de l'ouverture de la période : {$new_periode} (qte_ouverture_n = qte_cloture_n-1)");

        Periode::firstOrCreate(['periode' => $new_periode], ['statut' => 'OUVERTE']);

        // ouvrir TOUS les stocks de la nouvelle période à partir de la clôture précédente
        StockMensuel::where('periode', $prev_periode)
            ->get(['parkod', 'code_stock', 'qte_cloture', 'val_cloture'])
            ->each(function ($s) use ($new_periode) {
                $qte = $s->qte_cloture;
                $val = $s->val_cloture;

                Log::channel('stderr')->info("Creation Stock Mensuel : " . $s->parkod . "_" . $new_periode ."_". $s->code_stock);

                StockMensuel::firstOrCreate(
                    // clé d'unicité : on ne recrée pas une ligne déjà existante
                    [
                        'parkod'     => $s->parkod,
                        'periode'    => $new_periode,
                        'code_stock' => $s->code_stock,
                    ],
                    [
                        'qte_ouverture' => $qte,
                        'val_ouverture' => $val,
                        'qte_in'  => 0, 'val_in'  => 0,
                        'qte_out' => 0, 'val_out' => 0,
                        'qte_stk' => 0, 'val_stk' => 0,
                        'qte_cloture'  => $qte,
                        'val_cloture'  => $val,
                        'pamp_cloture' => $qte > 0 ? round($val / $qte, 4) : 0,
                    ]
                );
            });

        Log::channel('stderr')->info("l'ouverture de la période : {$new_periode} OK");
    }

    // lecture
    public function read(string $path, string $separateur = ';'): array
    {
        $fullPath = Storage::disk('local')->path($path);
        $lignes = [];

        if (($handle = fopen($fullPath, 'r')) === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier.");
        }

        $entetes = $this->versUtf8(fgetcsv($handle, 0, $separateur));

        while (($row = fgetcsv($handle, 0, $separateur)) !== false) {
            $lignes[] = array_combine($entetes, $this->versUtf8($row));
        }

        fclose($handle);

        return $lignes;
    }
    private function versUtf8(array $valeurs): array
    {
        return array_map(function ($v) {
            if ($v === null) {
                return null;
            }

            // déjà en UTF-8 ? on ne touche pas
            if (mb_check_encoding($v, 'UTF-8')) {
                return trim($v);
            }

            return trim(mb_convert_encoding($v, 'UTF-8', 'Windows-1252'));
        }, $valeurs);
    }

    // recalculation du stock mensuel
    protected function recalculerStockMensuel(string $parkod, string $periode, string $societe): void
    {
        Log::channel('stderr')->info("recalculer Stock Mensuel : " . $parkod . "_" . $periode ."_". $societe);

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

        $ecart = EtatStockWinparf::query()
            ->where('code_stock', $societe)
            ->whereBetween('date_comptable', [$debutMois, $finMois])
            ->whereHas('mouvementBrut', fn ($q) => $q->where('parkod', $parkod))
            ->selectRaw('COALESCE(SUM(qte), 0) as qte, COALESCE(SUM(valeur), 0) as valeur')
            ->toBase()
            ->first();

        // somnme des ecarts de stock de la periode
        $STOCK->qte_stk = $ecart->qte ?? 0;
        $STOCK->val_stk = $ecart->valeur ?? 0;

        // application de la formule [qte_cloture = qte_ouverture + qte_in + qte_out(negatif) + qte_stk( + ou -)] 
        $STOCK->qte_cloture = $STOCK->qte_ouverture + $STOCK->qte_in + $STOCK->qte_out + $STOCK->qte_stk;
        $STOCK->val_cloture = $STOCK->val_ouverture + $STOCK->val_in + $STOCK->val_out + $STOCK->val_stk;

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
    public function import_file($path){
        try{

            // trie ASC par rapport au date de mouvement
            $rows = collect($this->read($path))->sortBy([
                ['DATE_MVT', 'asc'],
                ['SEQUENCE', 'asc'],
            ])->values()->all(); 

            $touches = []; // variable de recalculation du stock mensuel

            //sauvegarde du batch (exemple)
            $BATCH = ImportBatch::create([
                'nom_fichier' => basename($path),
                'importe_le' => now(),
                'date_min' => collect($rows)->min('DATE_MVT'),
                'date_max' => collect($rows)->max('DATE_MVT'),
                'nb_ligne' => count($rows)
            ]);

            Log::channel('stderr')->info(count($rows) . " ligne(s)");

            DB::beginTransaction();

            foreach($rows as $row){

                Log::channel('stderr')->info($row['PARKOD']); 

                //avoir la periode
                $PERIODE_MVT = substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2);

                //creation marque | creation/modification article
                $MARQUE = Marque::firstOrCreate(
                    ["nom" => $row['MARQUE']],
                    ["nom" => $row['MARQUE']]
                );

                if($row['EAN'] != ""){
                    $ARTICLE = Article::updateOrCreate(
                        ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id],
                        ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id, 'ean' => $row['EAN'], 'description' => $row['DESCR_ARTICLE'], 'paht' => $row['PAHT']]
                    );
                }else{
                    $ARTICLE = Article::updateOrCreate(
                        ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id],
                        ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id, 'description' => $row['DESCR_ARTICLE'], 'paht' => $row['PAHT']]
                    );
                }

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
                        'ean' => $row['EAN'],
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

                    //Application du prorata (repartition du stock en 50-50 entre ADV et COSMA)
                    $signe = ($row['QTE'] > 0) ? 1 : -1;
                    $qte = abs($row['QTE']);
                    $val_unit = abs($row['MONTANT_IN_OUT'])/$qte;

                    $qte_cosma = intdiv($qte, 2);
                    $qte_adv = $qte - $qte_cosma;

                    //creattion du etat stock de winparf
                    $INV_WP_ADV = EtatStockWinparf::updateOrCreate(
                        ['mouvement_brut_id' =>  $MVT_BRUT->id, 'code_stock' => "ADV"],
                        [
                            'qte' => $qte_adv * $signe,
                            'valeur' => $val_unit * $qte_adv * $signe,
                            'date_comptable' => substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2) . "-" . substr($row['DATE_MVT'], 6, 2)
                        ]
                    );

                    $INV_WP_COSMA = EtatStockWinparf::updateOrCreate(
                        ['mouvement_brut_id' =>  $MVT_BRUT->id, 'code_stock' => "COSMA"],
                        [
                            'qte' => $qte_cosma * $signe,
                            'valeur' => $val_unit * $qte_cosma * $signe,
                            'date_comptable' => substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2) . "-" . substr($row['DATE_MVT'], 6, 2)
                        ]
                    );

                    // mettre en touche le siblings pour recalculer le stock mensuel (dual verification/calculation)
                    $siblings = ($row['SOCIETE'] == "ADV") ? "COSMA" : "ADV";
                    $touches[$row['PARKOD'] . '|' . $PERIODE_MVT . '|' . $siblings] = [
                        'parkod'  => $row['PARKOD'],
                        'periode' => $PERIODE_MVT,
                        'societe' => $siblings,
                    ];

                    // $INV_WP = EtatStockWinparf::updateOrCreate(
                    //     ['mouvement_brut_id' =>  $MVT_BRUT->id],
                    //     [
                    //         'mouvement_brut_id' => $MVT_BRUT->id,
                    //         'code_stock' => $row['SOCIETE'],
                    //         'qte' => $row['QTE'],
                    //         'valeur' => $row['MONTANT_IN_OUT'],
                    //         'date_comptable' => substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2) . "-" . substr($row['DATE_MVT'], 6, 2)
                    //     ]
                    // );
                }

                //sauvegarde des mouvement in et out
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

            if (isset($BATCH)) {
                $BATCH->statut = 'FAILED';
                $BATCH->save();
            }

            throw $e;
        }
    }

    // init stock initial
    public function initStock202609(){
        try{
            DB::beginTransaction();

            // trie ASC par rapport au date de mouvement
            $rows = collect($this->read("depot_winparf/init/INIT-STOCK-ASV_20260915_000000.TXT"))->sortBy([
                ['DATE_MVT', 'asc'],
                ['SEQUENCE', 'asc'],
            ])->values()->all(); 

            Log::channel('stderr')->info(count($rows) . " ligne(s)");

            foreach($rows as $row){
                Log::channel('stderr')->info($row['PARKOD']);                

                //creation marque | creation/modification article
                $MARQUE = Marque::firstOrCreate(
                    ["nom" => $row['MARQUE']],
                    ["nom" => $row['MARQUE']]
                );
                $ARTICLE = Article::updateOrCreate(
                    ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id],
                    ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->id, 'ean' => $row['EAN'], 'description' => $row['DESCR_ARTICLE'], 'paht' => $row['PAHT']]
                );

                //avoir la periode
                $PERIODE_MVT = "2026-09";

                StockMensuel::firstOrCreate(
                    ['parkod' => $row['PARKOD'], 'periode' => $PERIODE_MVT, 'code_stock' => $row['SOCIETE']],
                    [
                        'qte_ouverture' => $row['QTE'],
                        'val_ouverture' => $row['MONTANT_IN_OUT'],
                        'qte_in' => 0, 'val_in' => 0,
                        'qte_out' => 0, 'val_out' => 0,
                        'qte_stk' => 0, 'val_stk' => 0,
                        'qte_cloture' => $row['QTE'],
                        'val_cloture' => $row['MONTANT_IN_OUT'],
                        'pamp_cloture' => $row['PAMP'] > 0 ? round($row['MONTANT_IN_OUT'] / $row['QTE'], 4) : 0,
                    ]
                );

                // creation du stock virtuel soeur du code stock (ADV / COSMA)
                $code_stock_siblings =  ($row['SOCIETE'] == "ADV") ? "COSMA" : "ADV";  
                StockMensuel::firstOrCreate(
                    ['parkod' => $row['PARKOD'], 'periode' => $PERIODE_MVT, 'code_stock' => $code_stock_siblings],
                    [
                        'qte_ouverture' => 0,
                        'val_ouverture' => 0,
                        'qte_in' => 0, 'val_in' => 0,
                        'qte_out' => 0, 'val_out' => 0,
                        'qte_stk' => 0, 'val_stk' => 0,
                        'qte_cloture' => 0,
                        'val_cloture' => 0,
                        'pamp_cloture' => 0,
                    ]
                );
            }
            
            DB::commit();
            return true;

        }catch(Exception $e){
            DB::rollBack();
            throw $e;
        }
    }


}
