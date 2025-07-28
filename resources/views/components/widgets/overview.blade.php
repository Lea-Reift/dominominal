@php
    $columns = $this->getColumns();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);

    $stats = collect($this->getCachedStats());
    $mainStats = $stats->take(4);
    $extraStats = $stats->take(($stats->count() - 4) * -1);
@endphp

<x-filament::widget>
    <div x-data="{ expanded: false }" class="space-y-2">

        <div class="flex items-center justify-between">
            @if ($hasHeading || $hasDescription)
                <div class="fi-wi-stats-overview-header grid gap-y-1">
                    @if ($hasHeading)
                        <h3
                            class="fi-wi-stats-overview-header-heading col-span-full text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            {{ $heading }}
                        </h3>
                    @endif

                    @if ($hasDescription)
                        <p
                            class="fi-wi-stats-overview-header-description overflow-hidden break-words text-sm text-gray-500 dark:text-gray-400">
                            {{ $description }}
                        </p>
                    @endif
                </div>
            @endif


            <x-filament::button @click="expanded = false" x-show="expanded" color="warning">
                Ocultar Totales Adicionales
            </x-filament::button>
            <x-filament::button @click="expanded = true" x-show="!expanded" color="warning">
                Mostrar Totales Adicionales
            </x-filament::button>
        </div>

        <div class="fi-wi-stats-overview-stats-ctn grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($mainStats as $stat)
                {{ $stat }}
            @endforeach
        </div>

        <div x-show="expanded" x-collapse>
            <div class="fi-wi-stats-overview-stats-ctn grid gap-2 md:grid-cols-3">
                @foreach ($extraStats as $stat)
                    {{ $stat }}
                @endforeach
            </div>
        </div>
    </div>
</x-filament::widget>
