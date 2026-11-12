<?php

namespace App\Console\Commands;

use App\Models\WinparfImportLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

#[Signature('app:listen-winparf-files {--interval=5 : Secondes entre deux vérifications} {--stable=3 : Secondes sans modification avant de considérer le fichier complet}')]
#[Description('Surveille le dossier received et lance app:import-winparf-file quand un fichier .TXT est présent')]
class ListenWinparfFiles extends Command
{
    private ?BufferedOutput $active = null;

    public function handle(): int
    {
        $dir = storage_path('app/private/depot_winparf/received');
        $interval = max(1, (int) $this->option('interval'));
        $stable = max(0, (int) $this->option('stable'));

        // Copie les Log::info/warning/error de l'import dans la sortie affichée dans Filament
        Log::listen(function (MessageLogged $event) {
            if ($this->active) {
                $this->active->writeln('['.now()->format('H:i:s').'] '.strtoupper($event->level).': '.$event->message);
            }
        });

        $this->info("Surveillance de {$dir} (toutes les {$interval}s)");

        while (true) {
            $run = null;

            try {
                $files = $this->readyFiles($dir, $stable);

                if ($files !== []) {
                    $this->info('['.now()->toDateTimeString().'] Fichier détecté, import en cours...');
                    Log::info('winparf: fichier détecté, lancement de l\'import');

                    DB::reconnect();

                    $run = WinparfImportLog::create([
                        'status' => 'running',
                        'files' => implode(', ', $files),
                        'output' => '',
                        'started_at' => now(),
                    ]);

                    $output = $this->liveOutput($run);
                    $this->active = $output;

                    try {
                        $code = Artisan::call('app:import-winparf-file', [], $output);
                    } finally {
                        $this->active = null;
                    }

                    $run->update([
                        'status' => $code === 0 ? 'success' : 'failed',
                        'exit_code' => $code,
                        'output' => $output->all(),
                        'finished_at' => now(),
                    ]);

                    $this->line(trim($output->all()));

                    if ($code !== 0) {
                        Log::error("winparf: import terminé avec le code {$code}");
                    }
                }
            } catch (\Throwable $e) {
                $this->active = null;
                Log::error('winparf listener: '.$e->getMessage());
                $this->error($e->getMessage());

                try {
                    $run?->update([
                        'status' => 'failed',
                        'output' => ($run->output ?? '')."\n[ERREUR] ".$e->getMessage(),
                        'finished_at' => now(),
                    ]);
                } catch (\Throwable) {
                }
            }

            sleep($interval);
        }
    }

    /** Sortie qui s'écrit en base au fil de l'exécution (max 1 écriture/s). */
    private function liveOutput(WinparfImportLog $run): BufferedOutput
    {
        return new class($run) extends BufferedOutput
        {
            private string $all = '';

            private float $lastFlush = 0;

            public function __construct(private WinparfImportLog $run)
            {
                parent::__construct();
            }

            protected function doWrite(string $message, bool $newline): void
            {
                $this->all .= $message.($newline ? PHP_EOL : '');

                if (microtime(true) - $this->lastFlush >= 1) {
                    $this->lastFlush = microtime(true);
                    $this->run->update(['output' => $this->all]);
                }
            }

            public function all(): string
            {
                return $this->all;
            }
        };
    }

    /** @return string[] */
    private function readyFiles(string $dir, int $stable): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        clearstatcache();
        $ready = [];

        foreach (scandir($dir) as $name) {
            $path = $dir.DIRECTORY_SEPARATOR.$name;

            if (! is_file($path) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'txt') {
                continue;
            }

            if ((time() - filemtime($path)) >= $stable) {
                $ready[] = $name;
            }
        }

        return $ready;
    }
}
