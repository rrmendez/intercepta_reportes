<div class="flex flex-col items-end gap-1" wire:key="import-visit-wizard-submit">
    <x-filament::button
        type="button"
        color="warning"
        size="lg"
        wire:click="processImport"
        :disabled="! $canProcessImport"
        wire:loading.attr="disabled"
        wire:target="processImport"
    >
        <span wire:loading.remove wire:target="processImport">
            Procesar importacion
        </span>
        <span wire:loading wire:target="processImport">
            Procesando...
        </span>
    </x-filament::button>

    @unless ($canProcessImport)
        <p class="max-w-md text-right text-xs text-gray-500 dark:text-gray-400">
            Ningun archivo esta listo para importar. Corrige los errores o vuelve al paso anterior.
        </p>
    @endunless
</div>
