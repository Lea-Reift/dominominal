@php
    $emailForm = $this->getEmailSettingsForm();
@endphp
<x-filament::page>
    <x-filament::section heading="Correo Electrónico"  class="mb-6">
        <form wire:submit.prevent="submitEmailSettings" class="space-y-4">
            {{ $emailForm }}
            <x-filament::button type="submit" wire:target="submitEmailSettings" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submitEmailSettings">Guardar General</span>
                <span wire:loading wire:target="submitEmailSettings">Guardando...</span>
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament::page>
