<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\Report;
use App\Models\Template;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportBladeVariableReference;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
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
use Illuminate\Support\HtmlString;

class EditClientTemplate extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'Plantilla PDF';

    protected string $view = 'filament.resources.clients.pages.edit-client-template';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $templateId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $template = $this->resolveTemplate();
        $this->templateId = $template->id;

        $this->form->fill([
            'name' => $template->name,
            'pdf_template' => ReportPdfTemplateDefaults::editableBladeSource($template->pdf_template ?? ReportPdfTemplateDefaults::bladeSourceForClient($this->getClient())),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Plantilla PDF (Blade / HTML)')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la plantilla')
                            ->required()
                            ->maxLength(255),
                        Tabs::make('pdfTemplateTabs')
                            ->tabs([
                                Tab::make('preview')
                                    ->label('Vista previa')
                                    ->schema([
                                        Placeholder::make('html_preview')
                                            ->hiddenLabel()
                                            ->content(fn (Get $get): HtmlString => $this->previewHtml($get)),
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
                                        Placeholder::make('blade_variables')
                                            ->hiddenLabel()
                                            ->content(fn (): Htmlable => $this->variablesTabContent()),
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
                    ->id('client-template-editor-form')
                    ->livewireSubmitHandler('save'),
            ]);
    }

    public function save(): void
    {
        $template = $this->getTemplate();
        $state = $this->form->getState();

        $template->update([
            'name' => (string) $state['name'],
            'pdf_template' => (string) ($state['pdf_template'] ?? $template->pdf_template ?? ''),
            'active' => true,
        ]);

        Notification::make()
            ->title('Plantilla guardada')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'Plantilla PDF de '.$this->getClient()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bladeVariables')
                ->label('Variables disponibles')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->modalHeading('Variables en la plantilla')
                ->modalDescription('La plantilla se compila con Blade::render. Solo personal de confianza debe editar Blade (equivale a ejecutar PHP). Variables tipicas: $client, $report, $period_label, $visits (array de filas con las mismas claves que la tabla de visitas), $visit_reports, $visits_count, $total_observations, $total_quantity, $totals_by_bird_type, $totals_by_location.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }

    private function previewHtml(Get $get): HtmlString
    {
        $client = $this->getClient();
        $period = $this->loadTemplateEditorPreviewPeriod();

        $report = Report::make([
            'client_id' => $client->id,
            'date_from' => $period['date_from']->toDateString(),
            'date_until' => $period['date_until']->toDateString(),
            'generated_at' => now(),
        ])->setRelation('client', $client);

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
            '<div x-ignore class="max-h-[70vh] overflow-auto rounded-xl border border-gray-200 bg-white p-4 text-gray-950 shadow-sm dark:border-gray-700">'
            .$safeHtml
            .'</div>',
        );
    }

    private function variablesTabContent(): Htmlable
    {
        $client = $this->getClient();
        $period = $this->loadTemplateEditorPreviewPeriod();

        $report = Report::make([
            'client_id' => $client->id,
            'date_from' => $period['date_from']->toDateString(),
            'date_until' => $period['date_until']->toDateString(),
            'generated_at' => now(),
        ])->setRelation('client', $client);

        $bladeData = app(ReportBladeStringRenderer::class)->bladeData($client, $report, $period);

        $note = 'En esta pantalla $visits es un array de filas con las mismas claves que la tabla de visitas (mes de vista previa: ultimo mes con visitas o mes actual), igual que en la vista previa del PDF.';

        return app(ReportBladeVariableReference::class)->toHtml($bladeData, $note);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTemplateEditorPreviewPeriod(): array
    {
        $client = $this->getClient();
        $periodStart = $this->resolvePreviewPeriodStart($client);

        return app(ReportPeriodData::class)->load($client, $periodStart, $periodStart->endOfMonth(), null);
    }

    private function getClient(): Client
    {
        /** @var Client $client */
        $client = $this->getRecord();

        return $client;
    }

    private function resolveTemplate(): Template
    {
        $client = $this->getClient();

        $template = $client->templates()
            ->where('active', true)
            ->latest('id')
            ->first();

        if ($template instanceof Template) {
            return $template;
        }

        return $client->templates()->create([
            'name' => ReportPdfTemplateDefaults::suggestedName($client),
            'pdf_template' => ReportPdfTemplateDefaults::bladeSourceForClient($client),
            'active' => true,
        ]);
    }

    private function getTemplate(): Template
    {
        return Template::query()->whereKey($this->templateId)->firstOrFail();
    }

    private function resolvePreviewPeriodStart(Client $client): CarbonImmutable
    {
        $lastVisitDate = $client->visits()
            ->whereNotNull('date_init')
            ->latest('date_init')
            ->value('date_init');

        if ($lastVisitDate !== null) {
            return CarbonImmutable::parse($lastVisitDate)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
