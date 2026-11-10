<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\FileProcessor;

#[Signature('app:init_stock_2026_09')]
#[Description('lance la lecture et l ecriture du stock avec le stock initial du 2026 09')]
class init_stock_2026_09 extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FileProcessor $processor)
    {
        $this->info("initialisation du stock 2026 09...");
        try {

            $processor->initStock202609();
            $this->info("Le stock a ete initialise pour la periode de 2026 09");
            return self::SUCCESS;

        }catch(\Throwable $e){
            $this->error("ECHEC -> " . $e->getMessage());
            report($e);
            // on s'arrête : les fichiers suivants dépendent de celui-ci
            return self::FAILURE;
        }
    }
}
