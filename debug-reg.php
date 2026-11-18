<?php

use Illuminate\Support\Facades\DB;

$s = app(\App\Services\Regularisation::class);

$c = $s->exportRegularisation(filtre: 'A_REGULARISER')
    ->first(fn ($r) => $r->cas === 'COMPENSABLE_TOTAL');

if (! $c) {
    echo "Aucune ligne COMPENSABLE_TOTAL\n";
    return;
}

echo "Ligne testée : {$c->periode} / {$c->parkod}\n";

$m = new ReflectionMethod($s, 'regulariserUneLigne');
$m->setAccessible(true);

DB::beginTransaction(); // tout est annulé à la fin, même en cas de succès
try {
    $r = $m->invoke($s, $c->periode, $c->parkod, 'TEST' . time(), null, 1);
    echo is_string($r) ? "IGNORÉE : {$r}\n" : "OK, reg id {$r->id}\n";
} catch (Throwable $e) {
    echo get_class($e) . "\n" . $e->getMessage() . "\n"
        . $e->getFile() . ':' . $e->getLine() . "\n";
}
DB::rollBack();
