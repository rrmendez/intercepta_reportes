<?php

namespace App\Filament\Resources\Clients\Pages;

use App\ClientImportMode;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\User;
use App\Services\BasePdfTemplateService;
use App\Services\DevPdfReportSample;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportHtmlPreview;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportBladeVariableReference;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class EditBasePdfTemplate extends Page
{
    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'Plantilla PDF base';

    protected string $view = 'filament.resources.clients.pages.edit-client-template';

    public string $importMode = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user instanceof User && Gate::forUser($user)->allows('viewAny', Client::class);
    }

    public function mount(): void
    {
        $mode = $this->resolvedImportMode();

        $this->form->fill([
            'pdf_template' => app(BasePdfTemplateService::class)->readEditableSource($mode),
        ]);
    }

    private function resolvedImportMode(): ClientImportMode
    {
        $mode = ClientImportMode::tryFrom($this->importMode);
        abort_unless($mode instanceof ClientImportMode, 404);

        return $mode;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Plantilla PDF base (Blade / HTML)')
                    ->description('Esta plantilla se usa como predeterminada para clientes con el mismo modo de importación. Los clientes pueden personalizar su copia desde la plantilla activa del cliente.')
                    ->schema([
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
        $state = $this->form->getState();

        app(BasePdfTemplateService::class)->save(
            $this->resolvedImportMode(),
            (string) ($state['pdf_template'] ?? ''),
        );

        Notification::make()
            ->title('Plantilla base guardada')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'Plantilla base: '.$this->resolvedImportMode()->filamentLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restoreBasicTemplate')
                ->label('Restaurar plantilla basica')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Restaurar plantilla basica')
                ->modalDescription('Se reemplazara el contenido del editor con la plantilla basica del archivo en disco. Los cambios no guardados se perderan. Guarda la plantilla para persistir la restauracion.')
                ->action(fn () => $this->restoreBasicTemplate()),
            Action::make('bladeVariables')
                ->label('Variables disponibles')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->modalHeading('Variables en la plantilla')
                ->modalDescription('La plantilla se compila con Blade. Use la pestaña Variables para ver cada variable en español con su descripción. Las variables principales incluyen $cliente, $informe, $etiqueta_periodo, $visitas, $fecha_desde_texto, $fecha_hasta_texto y textos editables como $texto_objetivo o $texto_conclusion. Los títulos de sección están fijos en el HTML de la plantilla.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }

    public function restoreBasicTemplate(): void
    {
        $this->form->fill([
            'pdf_template' => ReportPdfTemplateDefaults::basicExpandedSourceForMode($this->resolvedImportMode()),
        ]);

        Notification::make()
            ->title('Plantilla basica restaurada en el editor')
            ->body('Guarda la plantilla para aplicar los cambios al archivo base.')
            ->success()
            ->send();
    }

    private function previewHtml(Get $get): HtmlString
    {
        $sample = DevPdfReportSample::build($this->resolvedImportMode());
        $client = $sample['client'];
        $report = $sample['report'];
        $period = $sample['period'];

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

        $safeHtml = app(ReportHtmlPreview::class)->build(
            (string) $result['html'],
            $client,
            $report,
            (string) $period['period_label'],
        );

        return app(ReportHtmlPreview::class)->wrap($safeHtml);
    }

    private function variablesTabContent(): Htmlable
    {
        $sample = DevPdfReportSample::build($this->resolvedImportMode());
        $client = $sample['client'];
        $report = $sample['report'];
        $period = $sample['period'];

        $bladeData = app(ReportBladeStringRenderer::class)->bladeData($client, $report, $period);

        $note = 'Vista previa con datos de demostración para este tipo de plantilla. Todas las variables están en español; consulte la columna Resumen para saber qué representa cada una.';

        return app(ReportBladeVariableReference::class)->toHtml($bladeData, $note);
    }
}
