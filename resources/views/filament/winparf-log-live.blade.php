@php
    $log = \App\Models\WinparfImportLog::find($getRecord()->id);
    $stalled = $log?->status === 'running' && $log->updated_at?->lt(now()->subMinutes(5));
    $running = $log?->status === 'running' && ! $stalled;
    $label = $stalled ? 'Interrompu' : match ($log?->status) {
        'running' => 'En cours',
        'success' => 'Terminé',
        default => 'Échec',
    };
    $color = $stalled ? '#dc2626' : match ($log?->status) {
        'running' => '#d97706',
        'success' => '#16a34a',
        default => '#dc2626',
    };
@endphp

<div @if ($running) wire:poll.1s @endif style="width: 100%;">
    <div style="display: flex; gap: 24px; margin-bottom: 12px; font-size: 14px;">
        <div>
            <strong>Statut :</strong>
            <span style="color: {{ $color }}; font-weight: 600;">{{ $label }}</span>
        </div>
        <div><strong>Code retour :</strong> {{ $log?->exit_code ?? '-' }}</div>
        <div><strong>Fin :</strong> {{ $log?->finished_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
    </div>

    <pre
        x-data
        x-init="$el.scrollTop = $el.scrollHeight"
        x-effect="$el.scrollTop = $el.scrollHeight"
        style="white-space: pre-wrap; font-family: monospace; font-size: 12px; max-height: 60vh; overflow: auto; background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 8px; margin: 0;"
    >{{ $log?->output ?: 'Aucune sortie' }}</pre>
</div>
