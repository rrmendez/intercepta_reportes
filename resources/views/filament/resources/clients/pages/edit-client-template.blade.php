<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->content }}

        <div class="flex justify-end">
            <x-filament::button
                type="submit"
                form-id="client-template-editor-form"
            >
                Guardar plantilla
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
