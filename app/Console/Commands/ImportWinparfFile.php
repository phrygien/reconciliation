<?php

namespace App\Console\Commands;

use App\Services\FileProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
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
        $this->info("Début de l'import...");

        try {
            $processor->import_file();
            $this->info('Import terminé avec succès.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Échec de l'import : " . $e);

            return self::FAILURE;
        }
    }
}
