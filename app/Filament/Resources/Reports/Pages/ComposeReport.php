<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use App\Mail\ReportPdfEmail;
use App\Models\Client;
use App\Models\Report;
use App\ReportStatus;
use App\Services\GenerateMonthlyReportPdfService;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportBladeVariableReference;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ComposeReport extends Page
{
    public const string EDITOR_DATA_KEY = 'editor_pdf_template';

    protected static string $resource = ReportResource::class;

    protected static ?string $title = 'Componer reporte';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.reports.pages.compose-report';

    #[Url]
    public ?int $report_id = null;

    #[Url]
    public ?int $client_id = null;

    #[Url]
    public ?string $date_from = null;

    #[Url]
    public ?string $date_until = null;

    #[Url]
    public ?int $template_id = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Filtros de la tabla de visitas (Livewire), alineados con la vista previa y el PDF.
     *
     * @var array<string, mixed>
     */
    public array $previewSpreadsheetFilters = [];

    public int $reportPreviewRevision = 0;

    public function mount(): void
    {
        $reportIdRaw = $this->report_id ?? request()->query('report_id');
        $this->report_id = filled($reportIdRaw) ? (int) $reportIdRaw : null;

        if ($this->report_id !== null && $this->report_id > 0) {
            $report = Report::query()->with('client')->findOrFail($this->report_id);
            Gate::authorize('view', $report);

            $client = $report->client;
            if ($client === null) {
                abort(404);
            }

            Gate::authorize('view', $client);

            [$from, $until] = app(ReportPeriodData::class)->normalizeRange(
                $report->date_from?->toDateString() ?? '',
                $report->date_until?->toDateString() ?? '',
            );

            $templateId = $report->template_id;
            $template = app(GenerateMonthlyReportPdfService::class)->resolveTemplateForClient($client, $templateId);

            $editorContent = is_array($report->data) ? ($report->data[self::EDITOR_DATA_KEY] ?? null) : null;
            $pdfTemplate = is_string($editorContent) && $editorContent !== ''
                ? $editorContent
                : ($template?->pdf_template ?? ReportPdfTemplateDefaults::bladeSourceForClient($client));
            $pdfTemplate = ReportPdfTemplateDefaults::editableBladeSource($pdfTemplate);

            $this->client_id = (int) $client->getKey();
            $this->date_from = $from->toDateString();
            $this->date_until = $until->toDateString();
            $this->template_id = $template?->id;

            $this->form->fill([
                'client_id' => (int) $client->getKey(),
                'date_from' => $from->toDateString(),
                'date_until' => $until->toDateString(),
                'template_id' => $template?->id,
                'pdf_template' => $pdfTemplate,
            ]);

            return;
        }

        Gate::authorize('create', Report::class);

        $clientId = (int) ($this->client_id ?? request()->query('client_id', 0));

        if ($clientId <= 0) {
            abort(404);
        }

        $client = Client::query()->findOrFail($clientId);
        Gate::authorize('view', $client);

        $defaultStart = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $dateFrom = (string) ($this->date_from ?? request()->query('date_from', $defaultStart->toDateString()));
        $dateUntil = (string) ($this->date_until ?? request()->query('date_until', $defaultStart->endOfMonth()->toDateString()));
        $templateIdQuery = $this->template_id ?? request()->query('template_id');

        [$from, $until] = app(ReportPeriodData::class)->normalizeRange($dateFrom, $dateUntil);

        $templateId = filled($templateIdQuery) ? (int) $templateIdQuery : null;
        $template = app(GenerateMonthlyReportPdfService::class)->resolveTemplateForClient($client, $templateId);

        $report = Report::query()
            ->where('client_id', $client->id)
            ->whereDate('date_from', $from->toDateString())
            ->whereDate('date_until', $until->toDateString())
            ->orderByDesc('id')
            ->first();

        $editorContent = is_array($report?->data) ? ($report->data[self::EDITOR_DATA_KEY] ?? null) : null;
        $pdfTemplate = is_string($editorContent) && $editorContent !== ''
            ? $editorContent
            : ($template?->pdf_template ?? ReportPdfTemplateDefaults::bladeSourceForClient($client));
        $pdfTemplate = ReportPdfTemplateDefaults::editableBladeSource($pdfTemplate);

        $this->client_id = $clientId;
        $this->date_from = $from->toDateString();
        $this->date_until = $until->toDateString();
        $this->template_id = $template?->id;

        $this->form->fill([
            'client_id' => $clientId,
            'date_from' => $from->toDateString(),
            'date_until' => $until->toDateString(),
            'template_id' => $template?->id,
            'pdf_template' => $pdfTemplate,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Contenido del reporte')
                    ->schema([
                        Hidden::make('client_id'),
                        Hidden::make('date_from'),
                        Hidden::make('date_until'),
                        Hidden::make('template_id'),
                        Tabs::make('composePdfTemplateTabs')
                            ->tabs([
                                Tab::make('preview')
                                    ->label('Vista previa')
                                    ->schema([
                                        Placeholder::make('compose_html_preview')
                                            ->hiddenLabel()
                                            ->content(fn (Get $get): HtmlString => $this->composeTemplatePreviewHtml($get)),
                                    ]),
                                Tab::make('source')
                                    ->label('Codigo Blade')
                                    ->schema([
                                        CodeEditor::make('pdf_template')
                                            ->label('Plantilla')
                                            ->language(Language::Html)
                                            ->columnSpanFull()
                                            ->live(debounce: 600),
                                    ]),
                                Tab::make('variables')
                                    ->label('Variables')
                                    ->schema([
                                        Placeholder::make('compose_blade_variables')
                                            ->hiddenLabel()
                                            ->content(fn (Get $get): Htmlable => $this->variablesTabContent($get)),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('compose-report-editor-form'),
            ]);
    }

    #[On('compose-report-range-changed')]
    public function syncRangeFromVisitsTable(string $dateFrom, string $dateUntil): void
    {
        $this->data['date_from'] = $dateFrom;
        $this->data['date_until'] = $dateUntil;
    }

    #[On('compose-report-spreadsheet-filters-changed')]
    public function syncSpreadsheetFiltersFromVisitsTable(array $filters): void
    {
        $this->previewSpreadsheetFilters = $filters;
        $this->reportPreviewRevision++;
    }

    #[On('report-period-visits-changed')]
    public function refreshReportPreviewAfterVisitChange(): void
    {
        $this->reportPreviewRevision++;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('visualizePdf')
                ->label('Visualizar PDF')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->action(fn () => $this->visualizePdf()),
            Action::make('saveDraft')
                ->label('Guardar')
                ->icon(Heroicon::OutlinedBookmark)
                ->action(fn () => $this->saveDraft()),
            Action::make('sendEmail')
                ->label('Enviar')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->disabled(fn (): bool => blank($this->getSelectedClient()?->email))
                ->tooltip(fn (): ?string => blank($this->getSelectedClient()?->email) ? 'El cliente no tiene correo configurado.' : null)
                ->action(fn () => $this->sendByEmail()),
        ];
    }

    public function visualizePdf(): void
    {
        $this->authorizeReportMutation();

        $url = $this->composePdfPreviewUrl();

        $this->js('window.open('.json_encode($url, JSON_THROW_ON_ERROR).', "_blank")');
    }

    public function saveDraft(): void
    {
        $this->authorizeReportMutation();

        $this->persistReport(ReportStatus::Draft, writePdfFile: false);

        Notification::make()
            ->title('Borrador guardado')
            ->success()
            ->send();
    }

    public function sendByEmail(): void
    {
        $this->authorizeReportMutation();

        $client = $this->getSelectedClient();

        if ($client === null || blank($client->email)) {
            Notification::make()
                ->title('Sin correo del cliente')
                ->body('Configura el correo del cliente antes de enviar.')
                ->danger()
                ->send();

            return;
        }

        $report = $this->persistReport(ReportStatus::Generated, writePdfFile: true);
        $path = $report->generated_file_path;

        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            Notification::make()
                ->title('No se pudo generar el PDF')
                ->danger()
                ->send();

            return;
        }

        $pdfBinary = Storage::disk('local')->get($path);
        $filename = basename((string) $path);

        Mail::to($client->email)->queue(
            (new ReportPdfEmail(
                report: $report,
                client: $client,
                pdfBinary: $pdfBinary,
                attachmentFilename: $filename,
            ))->afterCommit(),
        );

        $report->update([
            'status' => ReportStatus::Sent,
            'email_sent_at' => now(),
        ]);

        Notification::make()
            ->title('Correo en cola')
            ->body('Se enviara el PDF a '.$client->email.'.')
            ->success()
            ->send();
    }

    private function persistReport(ReportStatus $status, bool $writePdfFile): Report
    {
        $client = $this->getSelectedClient();
        if ($client === null) {
            throw new \RuntimeException('Cliente no encontrado.');
        }

        $state = $this->form->getState();
        [$from, $until] = app(ReportPeriodData::class)->normalizeRange(
            (string) $state['date_from'],
            (string) $state['date_until'],
        );

        $templateId = filled($state['template_id'] ?? null) ? (int) $state['template_id'] : null;
        $template = app(GenerateMonthlyReportPdfService::class)->resolveTemplateForClient($client, $templateId);

        $period = $this->resolveComposePeriod(null, $state);

        $existing = filled($this->report_id)
            ? Report::query()
                ->whereKey($this->report_id)
                ->where('client_id', $client->id)
                ->first()
            : Report::query()
                ->where('client_id', $client->id)
                ->whereDate('date_from', $from->toDateString())
                ->whereDate('date_until', $until->toDateString())
                ->orderByDesc('id')
                ->first();

        $editorPayload = $state['pdf_template'] ?? (is_array($existing?->data) ? ($existing->data[self::EDITOR_DATA_KEY] ?? null) : null);

        $mergedData = array_merge(
            $period['snapshot'],
            is_array($existing?->data) ? $existing->data : [],
            [self::EDITOR_DATA_KEY => $editorPayload],
        );

        return DB::transaction(function () use ($client, $template, $from, $until, $mergedData, $status, $writePdfFile, $state): Report {
            if (filled($this->report_id)) {
                $report = Report::query()
                    ->whereKey($this->report_id)
                    ->where('client_id', $client->id)
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    throw new \RuntimeException('Reporte no encontrado.');
                }
            } else {
                $report = Report::query()
                    ->where('client_id', $client->id)
                    ->whereDate('date_from', $from->toDateString())
                    ->whereDate('date_until', $until->toDateString())
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    $report = new Report([
                        'client_id' => $client->id,
                        'date_from' => $from->toDateString(),
                        'date_until' => $until->toDateString(),
                    ]);
                }
            }

            $report->fill([
                'month' => $from->month,
                'year' => $from->year,
                'template_id' => $template?->id,
                'status' => $status,
                'data' => $mergedData,
                'generated_by_user_id' => $report->generated_by_user_id ?? auth()->id(),
            ]);
            $report->save();

            if ($writePdfFile) {
                $report = $report->fresh();
                $periodWithReport = $this->resolveComposePeriod($report, $state);
                $pdfBinary = app(GenerateMonthlyReportPdfService::class)->renderPdfBinary(
                    client: $client,
                    report: $report,
                    template: $template,
                    period: $periodWithReport,
                    pdfTemplate: is_string($mergedData[self::EDITOR_DATA_KEY] ?? null)
                        ? $mergedData[self::EDITOR_DATA_KEY]
                        : $template?->pdf_template,
                );

                $path = app(GenerateMonthlyReportPdfService::class)->buildStoragePath($client, $from, $until);

                if (filled($report->generated_file_path) && $report->generated_file_path !== $path) {
                    Storage::disk('local')->delete($report->generated_file_path);
                }

                Storage::disk('local')->put($path, $pdfBinary);

                $report->update([
                    'generated_file_path' => $path,
                    'generated_at' => now(),
                ]);
            }

            return $report->fresh();
        });
    }

    private function composePdfPreviewUrl(): string
    {
        $binary = $this->buildCurrentPdfBinary();

        /** @var int|string|null $authId */
        $authId = auth()->id();
        $userId = is_numeric($authId) ? (int) $authId : 0;

        if ($userId <= 0) {
            abort(403);
        }

        $token = Str::lower(Str::random(40));

        Cache::put(
            "compose_report_pdf_preview:{$userId}:{$token}",
            $binary,
            now()->addMinutes(10),
        );

        $panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel();

        return $panel->route('reports.compose-pdf-preview', ['token' => $token]);
    }

    private function buildCurrentPdfBinary(): string
    {
        $client = $this->getSelectedClient();
        if ($client === null) {
            throw new \RuntimeException('Cliente no encontrado.');
        }

        $state = $this->form->getState();
        [$from, $until] = app(ReportPeriodData::class)->normalizeRange(
            (string) $state['date_from'],
            (string) $state['date_until'],
        );

        $report = Report::make([
            'client_id' => $client->id,
            'date_from' => $from->toDateString(),
            'date_until' => $until->toDateString(),
            'generated_at' => now(),
        ])->setRelation('client', $client);

        $templateId = filled($state['template_id'] ?? null) ? (int) $state['template_id'] : null;
        $template = app(GenerateMonthlyReportPdfService::class)->resolveTemplateForClient($client, $templateId);

        $period = $this->resolveComposePeriod($report, $state);

        $templateOverride = $state['pdf_template'] ?? null;
        $content = is_string($templateOverride) && $templateOverride !== ''
            ? $templateOverride
            : ($template?->pdf_template ?? ReportPdfTemplateDefaults::bladeSourceForClient($client));

        return app(GenerateMonthlyReportPdfService::class)->renderPdfBinary(
            client: $client,
            report: $report,
            template: $template,
            period: $period,
            pdfTemplate: $content,
        );
    }

    private function composeTemplatePreviewHtml(Get $get): HtmlString
    {
        $client = $this->getSelectedClient();
        $dateFrom = $get('date_from') ?? $this->data['date_from'] ?? null;
        $dateUntil = $get('date_until') ?? $this->data['date_until'] ?? null;

        if ($client === null || blank($dateFrom) || blank($dateUntil)) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Selecciona cliente y rango de fechas valido.</p>');
        }

        $formSlice = [
            'date_from' => (string) $dateFrom,
            'date_until' => (string) $dateUntil,
        ];

        $report = Report::make([
            'client_id' => $client->id,
            'date_from' => $formSlice['date_from'],
            'date_until' => $formSlice['date_until'],
            'generated_at' => now(),
        ])->setRelation('client', $client);

        $period = $this->resolveComposePeriod(null, $formSlice);

        $source = (string) ($get('pdf_template') ?? '');
        $renderer = app(ReportBladeStringRenderer::class);
        $result = $renderer->tryRenderDocument($source, $client, $report, $period);

        if (! $result['ok']) {
            return new HtmlString(
                '<div x-ignore class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-600 dark:bg-danger-950 dark:text-danger-100">'
                .e((string) ($result['message'] ?? 'Error desconocido'))
                .'</div>',
            );
        }

        $safeHtml = $renderer->htmlForAdminPreview((string) $result['html']);

        return new HtmlString(
            '<style>
                .report-html-preview .page-break,
                .report-html-preview .report-cover--page-break,
                .report-html-preview .report-initial-situation-page,
                .report-html-preview .report-objective-methodology-page,
                .report-html-preview .report-pdf-blank-page {
                    position: relative;
                    margin-bottom: 3.25rem !important;
                }

                .report-html-preview .page-break::after,
                .report-html-preview .report-cover--page-break::after,
                .report-html-preview .report-initial-situation-page::after,
                .report-html-preview .report-objective-methodology-page::after,
                .report-html-preview .report-pdf-blank-page::after {
                    content: "Salto de pagina";
                    position: absolute;
                    right: 0;
                    bottom: -2rem;
                    left: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    color: rgb(107 114 128);
                    font-size: 0.75rem;
                    font-weight: 600;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    white-space: nowrap;
                }

                .report-html-preview .page-break::after {
                    position: static;
                    margin-top: 2rem;
                }

                .report-html-preview .page-break::after,
                .report-html-preview .report-cover--page-break::after,
                .report-html-preview .report-initial-situation-page::after,
                .report-html-preview .report-objective-methodology-page::after,
                .report-html-preview .report-pdf-blank-page::after {
                    background: linear-gradient(
                        to bottom,
                        transparent calc(50% - 0.5px),
                        rgb(209 213 219) calc(50% - 0.5px),
                        rgb(209 213 219) calc(50% + 0.5px),
                        transparent calc(50% + 0.5px)
                    );
                    justify-content: center;
                }

                .report-html-preview .page-break::after {
                    content: "Salto de pagina";
                }
            </style>
            <div x-ignore data-report-preview-revision="'.$this->reportPreviewRevision.'" class="report-html-preview max-h-[70vh] overflow-auto rounded-xl border border-gray-200 bg-white p-4 text-gray-950 shadow-sm dark:border-gray-700">'
            .$safeHtml
            .'</div>',
        );
    }

    private function variablesTabContent(Get $get): Htmlable
    {
        $client = $this->getSelectedClient();
        $dateFrom = $get('date_from') ?? $this->data['date_from'] ?? null;
        $dateUntil = $get('date_until') ?? $this->data['date_until'] ?? null;

        if ($client === null || blank($dateFrom) || blank($dateUntil)) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Selecciona cliente y rango de fechas valido.</p>');
        }

        $formSlice = [
            'date_from' => (string) $dateFrom,
            'date_until' => (string) $dateUntil,
        ];

        $report = Report::make([
            'client_id' => $client->id,
            'date_from' => $formSlice['date_from'],
            'date_until' => $formSlice['date_until'],
            'generated_at' => now(),
        ])->setRelation('client', $client);

        $period = $this->resolveComposePeriod($report, $formSlice);
        $bladeData = app(ReportBladeStringRenderer::class)->bladeData($client, $report, $period);

        $note = '$visits es un array de filas (misma forma que la tabla de vista previa) con las visitas filtradas por cliente, modo de periodo y fechas.';

        return app(ReportBladeVariableReference::class)->toHtml($bladeData, $note);
    }

    /**
     * @param  array<string, mixed>|null  $formState  Fechas de respaldo cuando aun no llegan filtros desde la tabla.
     * @return array<string, mixed>
     */
    private function resolveComposePeriod(?Report $report, ?array $formState = null): array
    {
        $client = $this->getSelectedClient();

        if ($client === null) {
            throw new \RuntimeException('Cliente no encontrado.');
        }

        $formState ??= $this->data ?? [];

        try {
            return app(ReportPeriodData::class)->loadForSpreadsheetRow(
                $client,
                $this->previewSpreadsheetRow($formState),
                $report,
            );
        } catch (InvalidArgumentException) {
            [$from, $until] = app(ReportPeriodData::class)->normalizeRange(
                (string) ($formState['date_from'] ?? ''),
                (string) ($formState['date_until'] ?? ''),
            );

            return app(ReportPeriodData::class)->load($client, $from, $until, $report);
        }
    }

    /**
     * @param  array<string, mixed>  $formState
     * @return array<string, mixed>
     */
    private function previewSpreadsheetRow(?array $formState = null): array
    {
        $fromTable = (array) data_get($this->previewSpreadsheetFilters, 'spreadsheet', []);

        if ($fromTable !== []) {
            return $fromTable;
        }

        $formState ??= $this->data ?? [];

        return [
            'mode' => 'custom_range',
            'date_from' => $formState['date_from'] ?? null,
            'date_until' => $formState['date_until'] ?? null,
        ];
    }

    private function getSelectedClient(): ?Client
    {
        $clientId = $this->data['client_id'] ?? null;

        if (! filled($clientId)) {
            return null;
        }

        return Client::query()->find((int) $clientId);
    }

    private function findExistingReport(): ?Report
    {
        if (filled($this->report_id)) {
            $client = $this->getSelectedClient();

            return Report::query()
                ->whereKey($this->report_id)
                ->when($client !== null, fn ($query) => $query->where('client_id', $client->id))
                ->first();
        }

        $client = $this->getSelectedClient();
        $dateFrom = $this->data['date_from'] ?? null;
        $dateUntil = $this->data['date_until'] ?? null;

        if ($client === null || blank($dateFrom) || blank($dateUntil)) {
            return null;
        }

        return Report::query()
            ->where('client_id', $client->id)
            ->whereDate('date_from', $dateFrom)
            ->whereDate('date_until', $dateUntil)
            ->orderByDesc('id')
            ->first();
    }

    private function authorizeReportMutation(): void
    {
        if (filled($this->report_id)) {
            $report = Report::query()->find($this->report_id);

            if ($report === null) {
                abort(404);
            }

            Gate::authorize('update', $report);

            return;
        }

        Gate::authorize('create', Report::class);
    }
}
