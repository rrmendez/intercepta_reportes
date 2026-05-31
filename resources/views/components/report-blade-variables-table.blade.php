@props([
    /** @var list<array{name: string, type: string, summary: string, categoria?: string}> $rows */
    'rows' => [],
    'visitsContextNote' => null,
])

<div class="space-y-3 text-sm">
    @if (filled($visitsContextNote))
        <p class="text-gray-600 dark:text-gray-300">
            {{ $visitsContextNote }}
        </p>
    @endif

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800/80">
                <tr>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">
                        Sección
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">
                        Variable
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">
                        Tipo
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">
                        Resumen
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                            {{ $row['categoria'] ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-900 dark:text-gray-100">
                            {{ $row['name'] }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                            {{ $row['type'] }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                            {{ $row['summary'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
