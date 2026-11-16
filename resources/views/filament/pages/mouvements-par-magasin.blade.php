<x-filament-panels::page>
    {{-- Niveau 1 : sociétés --}}
    <div class="overflow-x-auto">
        <x-filament::tabs label="Sociétés">
            @foreach ($this->societes as $societe)
                <x-filament::tabs.item
                    :active="(string) $societeTab === (string) $societe->id"
                    wire:click="selectSociete('{{ $societe->id }}')"
                    wire:key="societe-{{ $societe->id }}"
                    icon="heroicon-o-building-office"
                >
                    {{ $societe->code_stock }}@if ($societe->libelle) · {{ $societe->libelle }}@endif
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    {{-- Niveau 2 : magasins de la société --}}
    <div class="overflow-x-auto">
        <x-filament::tabs label="Magasins">
            @forelse ($this->magasins as $mag)
                <x-filament::tabs.item
                    :active="$activeTab === $mag->mag_code"
                    wire:click="selectMagasin('{{ $mag->mag_code }}')"
                    wire:key="magasin-{{ $societeTab }}-{{ $mag->mag_code }}"
                    icon="heroicon-o-building-storefront"
                >
                    {{ $mag->mag_code }}@if ($mag->libelle) · {{ $mag->libelle }}@endif
                </x-filament::tabs.item>
            @empty
                <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    Aucun magasin pour cette société.
                </div>
            @endforelse
        </x-filament::tabs>
    </div>

    {{-- Totaux OUT --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Mouvements OUT</div>
            <div class="text-2xl font-semibold">{{ number_format($this->totaux['nb'], 0, ',', ' ') }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Qté OUT</div>
            <div class="text-2xl font-semibold text-danger-600">{{ number_format($this->totaux['qte_out'], 0, ',', ' ') }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Montant OUT</div>
            <div class="text-2xl font-semibold text-danger-600">{{ number_format($this->totaux['montant_out'], 2, ',', ' ') }}</div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
