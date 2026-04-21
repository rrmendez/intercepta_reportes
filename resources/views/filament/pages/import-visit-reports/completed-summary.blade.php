@php
    /** @var array{success: bool, message: string, imported_files: int, expected_files: int, total_rows: int, persisted_rows: int, skipped_rows: int, duration_seconds: float, file_errors: array<int, string>} $result */
    /** @var string $finishUrl */
    /** @var string $listVisitsUrl */
    $durationLabel = number_format($result['duration_seconds'], $result['duration_seconds'] >= 10 ? 0 : 1).'s';
@endphp

@if (! $result['success'])
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 dark:border-yellow-500/40 dark:bg-yellow-500/10 dark:text-yellow-200">
        No successful import was registered yet.
    </div>
@else
    <div class="relative overflow-hidden px-6 py-8 text-center sm:px-10 sm:py-12">
        <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-40">
            <div class="absolute -top-28 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-amber-400/25 blur-3xl dark:bg-amber-400/20"></div>
            <div class="absolute bottom-0 left-0 h-40 w-40 rounded-full bg-cyan-400/15 blur-2xl dark:bg-cyan-400/10"></div>
        </div>

        <div class="relative">
            <div class="mx-auto mb-8 grid h-28 w-28 place-items-center rounded-2xl border border-amber-300/35 bg-gradient-to-b from-amber-200/55 to-amber-100/30 dark:border-amber-300/20 dark:from-amber-300/20 dark:to-amber-500/10">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-300 text-amber-900 shadow-lg shadow-amber-300/35">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 7 9 18l-5-5" />
                    </svg>
                </span>
            </div>

            <h2 class="text-4xl leading-tight font-extrabold tracking-tight text-slate-900 sm:text-5xl dark:text-slate-100">
                Import Completed Successfully
            </h2>

            <p class="mx-auto mt-4 max-w-2xl text-base text-slate-600 sm:text-lg dark:text-slate-300/90">
                Your dataset was parsed, validated, and committed successfully.
            </p>

            <div class="mx-auto mt-8 grid max-w-3xl grid-cols-1 rounded-2xl border border-slate-200 bg-white/85 p-2 shadow-sm sm:grid-cols-3 sm:p-4 dark:border-slate-700/80 dark:bg-[#030b21]/80">
                <div class="py-4 sm:border-r sm:border-slate-200 dark:sm:border-slate-700/70">
                    <p class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400">Total Files</p>
                    <p class="mt-1 text-4xl font-bold text-amber-300">{{ $result['imported_files'] }}</p>
                </div>
                <div class="py-4 sm:border-r sm:border-slate-200 dark:sm:border-slate-700/70">
                    <p class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400">Total Rows</p>
                    <p class="mt-1 text-4xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($result['total_rows']) }}</p>
                </div>
                <div class="py-4">
                    <p class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400">Duration</p>
                    <p class="mt-1 text-4xl font-bold text-slate-900 dark:text-slate-100">{{ $durationLabel }}</p>
                </div>
            </div>

            @if ($result['file_errors'] !== [])
                <div class="mx-auto mt-5 max-w-3xl rounded-xl border border-rose-200 bg-rose-50 p-4 text-left dark:border-rose-300/30 dark:bg-rose-500/10">
                    <p class="mb-2 text-xs font-semibold tracking-[0.14em] text-rose-700 uppercase dark:text-rose-200">Imported with warnings</p>
                    <ul class="space-y-1">
                        @foreach (collect($result['file_errors'])->take(3) as $error)
                            <li class="text-sm text-rose-700 dark:text-rose-100/95">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <x-filament::button
                    tag="a"
                    :href="$finishUrl"
                    color="warning"
                    size="xl"
                >
                    Finish
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="$listVisitsUrl"
                    color="gray"
                    outlined
                    size="xl"
                >
                    View Records
                </x-filament::button>
            </div>
        </div>
    </div>
@endif
