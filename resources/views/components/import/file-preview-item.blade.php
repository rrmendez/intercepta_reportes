@php
    /** @var array<string, mixed> $entry */
    $canImport = (bool) ($entry['can_import'] ?? false);
    $invalidRows = (int) ($entry['invalid_rows'] ?? 0);
    $badgeLabel = $canImport ? 'Listo para importar' : 'Invalido';
    $errors = collect($entry['errors'] ?? [])->take(3)->all();
    $warnings = collect($entry['warnings'] ?? [])->take(5)->all();
@endphp

<article
    @class([
        'mb-4 rounded-2xl border p-5 shadow-sm transition-colors last:mb-0',
        'border-emerald-200 bg-emerald-50/20 dark:border-emerald-400/30 dark:bg-emerald-500/5' => $canImport,
        'border-rose-200 bg-rose-50/25 dark:border-rose-400/30 dark:bg-rose-500/5' => ! $canImport,
    ])
>
    <div class="flex items-start justify-between gap-4">
        <h4 class="m-0 break-words text-lg leading-tight font-bold text-slate-900 dark:text-slate-100">
            {{ (string) ($entry['display_file_name'] ?? $entry['file_name'] ?? '') }}
        </h4>
        <x-filament::badge size="xl" color="{{ $canImport ? 'success' : 'danger' }}">
            {{ $badgeLabel }}
        </x-filament::badge>
    </div>

    <div class="my-3.5 border-t border-slate-200 dark:border-slate-700/60"></div>

    <p class="m-0 text-slate-600 dark:text-slate-300">
        Filas: {{ (int) ($entry['total_rows'] ?? 0) }}
        | Validas: {{ (int) ($entry['valid_rows'] ?? 0) }}
        |
        <span
            @class([
                'font-bold text-rose-600 dark:text-rose-300' => $invalidRows > 0,
                'text-slate-600 dark:text-slate-300' => $invalidRows <= 0,
            ])
        >
            Invalidas: {{ $invalidRows }}
        </span>
    </p>

    @if ($errors !== [])
        <div class="mt-3.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm leading-relaxed text-rose-800 dark:border-rose-500/40 dark:bg-slate-900/70 dark:text-rose-100">
            @foreach ($errors as $message)
                <div>{{ (string) $message }}</div>
            @endforeach
        </div>
    @endif

    @if ($warnings !== [])
        <div class="mt-3.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm leading-relaxed text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            @foreach ($warnings as $message)
                <div>{{ (string) $message }}</div>
            @endforeach
        </div>
    @endif
</article>
