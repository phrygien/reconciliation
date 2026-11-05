<?php

namespace App\Console\Commands;

use App\Services\FileProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('app:import-winparf-file')]
#[Description('Importe le fichier de mouvements Winparf')]
class ImportWinparfFile extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FileProcessor $processor): int
    {
        $disk = Storage::disk('local');
        $base = 'depot_winparf';

        $fichiers = collect($disk->files("$base/received"))
            ->filter(fn ($f) => str_ends_with(strtoupper($f), '.TXT'))
            ->sort()      // ordre chronologique grâce à la date dans le nom
            ->values();

        if ($fichiers->isEmpty()) {
            $this->info('Aucun fichier à traiter.');
            return self::SUCCESS;
        }

        foreach ($fichiers as $fichier) {
            $nom = basename($fichier);
            $this->info("Traitement de $nom...");

            try {
                $processor->import_file($fichier);

                $disk->move($fichier, "$base/processed/$nom");
                $this->info("OK -> processed/$nom");

            } catch (\Throwable $e) {
                $disk->move($fichier, "$base/failed/$nom");
                $this->error("ECHEC -> failed/$nom : " . $e->getMessage());
                report($e);

                // on s'arrête : les fichiers suivants dépendent de celui-ci
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
