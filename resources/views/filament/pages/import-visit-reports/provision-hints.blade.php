@php
    /** @var array<int, array{file_name: string, hint: array<string, mixed>}> $rows */
@endphp

<div class="rounded-xl border border-amber-200/80 bg-amber-50/40 p-4 dark:border-amber-500/25 dark:bg-amber-950/20">
<ul class="m-0 list-none space-y-2 p-0">
    @foreach ($rows as $row)
        @php
            $hint = $row['hint'];
            $kind = $hint['kind'] ?? 'unparseable';
        @endphp
        <li class="rounded-lg border border-amber-200/60 bg-amber-50/40 p-3 dark:border-amber-800/40 dark:bg-amber-950/20">
            <p class="m-0 font-medium text-slate-900 dark:text-slate-100">{{ $row['file_name'] }}</p>
            @if ($kind === 'existing_client')
                <p class="mt-1 mb-0 text-sm text-slate-700 dark:text-slate-300">
                    Coincide con el cliente existente <span class="font-semibold">{{ $hint['matched_client_name'] ?? '' }}</span>
                    (nombre inferido: <span class="font-medium">{{ $hint['display_name'] ?? '' }}</span>).
                </p>
            @elseif ($kind === 'will_create_client')
                <p class="mt-1 mb-0 text-sm text-emerald-800 dark:text-emerald-200/95">
                    Se creara el cliente <span class="font-semibold">{{ $hint['display_name'] ?? '' }}</span> al procesar la importacion.
                </p>
            @elseif ($kind === 'error')
                <p class="mt-1 mb-0 text-sm text-rose-800 dark:text-rose-100/90">
                    {{ $hint['message'] ?? 'No se puede resolver el cliente de forma unica.' }}
                    @if (! empty($hint['display_name']))
                        <span class="mt-1 block text-xs opacity-90">Nombre inferido: {{ $hint['display_name'] }}</span>
                    @endif
                </p>
            @else
                <p class="mt-1 mb-0 text-sm text-slate-600 dark:text-slate-400">
                    {{ $hint['message'] ?? 'No se pudo inferir el nombre del cliente desde este archivo.' }}
                </p>
            @endif
        </li>
    @endforeach
</ul>
</div>
