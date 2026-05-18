@livewire(
    App\Livewire\ReportVisitsPreviewTable::class,
    [
        'clientId' => $clientId,
        'dateFrom' => $dateFrom,
        'dateUntil' => $dateUntil,
    ],
    key("report-visits-preview-{$clientId}-{$dateFrom}-{$dateUntil}")
)
