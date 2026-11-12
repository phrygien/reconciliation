<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

#[Signature('app:listen-winparf-files {--interval=5 : Secondes entre deux vérifications} {--stable=3 : Secondes sans modification avant de considérer le fichier complet}')]
#[Description('Surveille le dossier received et lance app:import-winparf-file quand un fichier .TXT est présent')]
class ListenWinparfFiles extends Command
{
    public function handle(): int
    {
        $dir = storage_path('app/private/depot_winparf/received');
        $interval = max(1, (int) $this->option('interval'));
        $stable = max(0, (int) $this->option('stable'));

        $this->info("Surveillance de {$dir} (toutes les {$interval}s)");

        while (true) {
            try {
                if ($this->hasReadyFile($dir, $stable)) {
                    $this->info('['.now()->toDateTimeString().'] Fichier détecté, import en cours...');
                    Log::info('winparf: fichier détecté, lancement de l\'import');

                    $code = Artisan::call('app:import-winparf-file');
                    $this->line(trim(Artisan::output()));

                    if ($code !== 0) {
                        Log::error("winparf: import terminé avec le code {$code}");
                    }
                }
            } catch (\Throwable $e) {
                Log::error('winparf listener: '.$e->getMessage());
                $this->error($e->getMessage());
            }

            sleep($interval);
        }
    }

    private function hasReadyFile(string $dir, int $stable): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        clearstatcache();

        foreach (scandir($dir) as $name) {
            $path = $dir.DIRECTORY_SEPARATOR.$name;

            if (! is_file($path) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'txt') {
                continue;
            }

            // Ignore les fichiers en cours d'upload SFTP
            if ((time() - filemtime($path)) >= $stable) {
                return true;
            }
        }

        return false;
    }
}
