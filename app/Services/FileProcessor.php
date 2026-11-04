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
                throw new Exception('La periode ouvert ne correspond pas a la periode du mouvement en question');
            }

            // chercher un stock mensuel, si il n'y a pas, il faut le creer
            $stock = StockMensuel::firstOrCreate(
                ['parkod' => $parkod, 'periode' => $annee_mois, 'code_stock' => $code_stock],          // critères de recherche
                [   'periode' => $parkod,
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

    // --------------------- //

    // read file mvt
    public function import_file(){
        try{

            $rows = $this->read("/depot_winparf/received/ADV-COSMA-MVT_20261020_013122.TXT"); // ligne de fichier

            //sauvegarde du batch (exemple)
            $BATCH = ImportBatch::create([
                'nom_fichier' => "ADV-COSMA-MVT_20261020_013122.TXT",
                'importe_le' => now(),
                'date_min' => min('DATE_MVT', $rows), 
                'date_max' => max('DATE_MVT', $rows),
                'nb_lignes' => count($rows)
            ]);

            foreach($rows as $row){

                //avoir la periode
                $PERIODE_MVT = substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2);

                //creation marque | creation/modification article
                $MARQUE = Marque::firstOrCreate(
                    ["nom" => $row['MARQUE']],
                    ["nom" => $row['MARQUE']]
                );
                $ARTICLE = Article::updateOrCreate(
                    ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->marque_id],
                    ['parkod' => $row['PARKOD'], 'marque_id' => $MARQUE->marque_id, 'description' => $row['DESCR_ARTICLE'], 'paht' => $row['PAHT']]
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
                    ["typ_mvt" => $row['TYP_MVT'], "sens" => $row['IN_OUT'], "categorie" => $categorie], // critères de recherche
                    ["typ_mvt" => $row['TYP_MVT'], "sens" => $row['IN_OUT'], "categorie" => $categorie] // valeurs ajoutées seulement à la création
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
                        'batch_id' => $BATCH->batch_id,
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
                        ['mouvement_brut_id' =>  $MVT_BRUT->mvt_id],
                        [
                            'mouvement_brut_id' => $MVT_BRUT->mvt_id,
                            'mag_orig' => $row['MAG_ORIG'],
                            'mag_dest' => $row['MAG_DEST'],
                            'code_stock' => $row['SOCIETE'],
                            'qte' => $row['QTE'],
                            'valeur' => $row['MONTANT_IN_OUT'],
                            'mode_affect' => MouvementAffecte::MODE_AUTO,
                            'date_comptable' => substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2) . "-" . substr($row['DATE_MVT'], 6, 2)
                        ]
                    );
                }
                if($row['IN_OUT'] == "STK"){
                    $INV_WP = EtatStockWinparf::updateOrCreate(
                        ['mouvement_brut_id' =>  $MVT_BRUT->mvt_id],
                        [
                            'mouvement_brut_id' => $MVT_BRUT->mvt_id,
                            'code_stock' => $row['SOCIETE'],
                            'qte' => $row['QTE'],
                            'valeur' => $row['MONTANT_IN_OUT'],
                            'date_comptable' => substr($row['DATE_MVT'], 0, 4) . "-" . substr($row['DATE_MVT'], 4, 2) . "-" . substr($row['DATE_MVT'], 6, 2)
                        ]
                    );
                }            
            }

        }catch(Exception $e){
            throw $e;
        }
    }


}
