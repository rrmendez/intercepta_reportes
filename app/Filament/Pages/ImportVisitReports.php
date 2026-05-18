<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VisitImports\VisitImportResource;
use App\Filament\Resources\Visits\VisitResource;
use App\Jobs\ProcessStoredVisitImportJob;
use App\Models\User;
use App\Rules\VisitImportStoredFileValid;
use App\Services\VisitImport\Helpers\VisitImportFileHelper;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ImportVisitReports extends Page
{
    public const IMPORT_RESULT_PENDING_PAYLOAD = '{"processed":false}';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'General';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Importar visitas';

    protected static ?string $title = 'Importar visitas';

    protected string $view = 'filament.pages.import-visit-reports';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isOperator();
    }

    public function mount(): void
    {
        $this->form->fill([
            'preview_payload' => '[]',
            'import_result_payload' => self::IMPORT_RESULT_PENDING_PAYLOAD,
            'provision_client_and_sections' => false,
            'replace_previous_import_same_filename' => false,
        ]);
    }

    public function processImport(): void
    {
        if (! $this->canProcessImport()) {
            $notification = Notification::make()
                ->title('No hay archivos listos para importar')
                ->body('Corrige los errores de validacion o sube otros archivos antes de procesar.')
                ->danger();
            $notification->send();
            $this->sendNotificationToDatabase($notification);

            return;
        }

        $data = $this->data ?? [];

        $importContext = [
            'provision_client_and_sections' => (bool) ($data['provision_client_and_sections'] ?? false),
            'replace_previous_import_same_filename' => (bool) ($data['replace_previous_import_same_filename'] ?? false),
        ];

        $preview = $this->decodePreviewPayload((string) ($data['preview_payload'] ?? '[]'));

        if ($preview === []) {
            $preview = $this->verifyUploadedFiles(
                files: (array) ($data['files'] ?? []),
                context: $importContext,
            );
            $data['preview_payload'] = json_encode($preview) ?: '[]';
        }

        $importResult = $this->importFromPreview($preview, $importContext);
        $importResult['processed'] = true;

        $data['import_result_payload'] = json_encode($importResult) ?: self::IMPORT_RESULT_PENDING_PAYLOAD;
        $this->data = $data;
        $this->form->fill($data);

        if (! $importResult['success']) {
            $notification = Notification::make()
                ->title('La importacion fallo')
                ->body((string) $importResult['message'])
                ->danger();
            $notification->send();
            $this->sendNotificationToDatabase($notification);

            return;
        }

        $notification = Notification::make()
            ->title(($importResult['queued'] ?? false) ? 'Importacion en proceso' : 'Importacion completada')
            ->body((string) $importResult['message'])
            ->success();
        $notification->send();
        $this->sendNotificationToDatabase($notification);

        $batchWarnings = $importResult['import_warnings'] ?? [];

        if ($batchWarnings !== []) {
            $warningNotification = Notification::make()
                ->title('Advertencias de importacion')
                ->body(collect($batchWarnings)->take(5)->implode("\n"))
                ->warning();
            $warningNotification->send();
            $this->sendNotificationToDatabase($warningNotification);
        }
    }

    private function sendNotificationToDatabase(Notification $notification): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $user->notifyNow($notification->toDatabase());
    }

    public function canProcessImport(): bool
    {
        $data = $this->data ?? [];
        $preview = $this->decodePreviewPayload((string) ($data['preview_payload'] ?? '[]'));

        if ($preview === []) {
            return false;
        }

        return collect($preview)
            ->contains(fn (array $entry): bool => (bool) ($entry['can_import'] ?? false));
    }

    public function isImportProcessed(): bool
    {
        $data = $this->data ?? [];

        return $this->decodeImportResultPayload(
            (string) ($data['import_result_payload'] ?? self::IMPORT_RESULT_PENDING_PAYLOAD),
        )['processed'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('Subir archivos')
                        ->description('Sube uno o mas archivos.')
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->completedIcon(Heroicon::OutlinedCheck)
                        ->schema([
                            FileUpload::make('files')
                                ->label('Archivos CSV / Excel')
                                ->directory('imports/visit-reports')
                                ->disk('local')
                                ->visibility('private')
                                ->preserveFilenames()
                                ->multiple()
                                ->acceptedFileTypes([
                                    'text/csv',
                                    'text/plain',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                ])
                                ->nestedRecursiveRule(fn (Get $get): VisitImportStoredFileValid => new VisitImportStoredFileValid(
                                    provisionClientAndSections: (bool) $get('provision_client_and_sections'),
                                    replacePreviousImportSameFilename: (bool) $get('replace_previous_import_same_filename'),
                                ))
                                ->live(debounce: 800)
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $this->refreshVisitImportPreviewFromUploadedFiles($get, $set);
                                })
                                ->required(),
                            Toggle::make('provision_client_and_sections')
                                ->label('Crear cliente y secciones desde el archivo si no existen')
                                ->helperText('El nombre del cliente se infiere solo del prefijo del archivo antes de Constancia_de_Servicio (guiones bajos como espacios y CamelCase). Debe coincidir exactamente con un cliente existente para reutilizarlo; si el prefijo difiere (p. ej. Cliente_Nuevo_Ejemplo_... frente a «Cliente Nuevo»), se crea otro cliente.')
                                ->default(false)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $this->refreshVisitImportPreviewFromUploadedFiles($get, $set);
                                })
                                ->columnSpanFull(),
                            Toggle::make('replace_previous_import_same_filename')
                                ->label('Eliminar visitas de importaciones anteriores con el mismo nombre de archivo')
                                ->helperText('Si ya se importo este nombre de archivo para el mismo cliente, borra las visitas ligadas a esas importaciones antes de volver a importar (correccion de errores).')
                                ->default(false)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $this->refreshVisitImportPreviewFromUploadedFiles($get, $set);
                                })
                                ->columnSpanFull(),
                            Hidden::make('preview_payload')
                                ->default('[]')
                                ->dehydrated(),
                            Hidden::make('import_result_payload')
                                ->default(self::IMPORT_RESULT_PENDING_PAYLOAD)
                                ->dehydrated(),
                        ]),
                    Step::make('Validar e importar')
                        ->description('Revisa la validacion y pulsa Procesar importacion en la barra inferior. Los datos se guardaran en unos minutos; podras ver el resultado en Importaciones de visitas.')
                        ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                        ->completedIcon(Heroicon::OutlinedCheck)
                        ->schema([
                            Placeholder::make('provision_client_hints')
                                ->hiddenLabel()
                                ->visible(fn (Get $get): bool => (bool) $get('provision_client_and_sections'))
                                ->content(fn (Get $get): HtmlString => new HtmlString(
                                    $this->renderProvisionClientHints($get),
                                )),
                            Placeholder::make('preview_results')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => new HtmlString(
                                    $this->renderPreviewSummary((string) $get('preview_payload')),
                                )),
                            Placeholder::make('import_completed_summary')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => new HtmlString(
                                    $this->renderProcessOrCompletedSummary((string) $get('import_result_payload')),
                                )),
                        ]),
                ])
                    ->nextAction(fn (Action $action): Action => $action->label('Siguiente'))
                    ->submitAction($this->createWizardSubmitAction()),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('import-visit-reports-form'),
            ]);
    }

    /**
     * @param  array<int, array{file_name: string, file_path: string, can_import: bool, total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>}>  $preview
     * @param  array{provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $importContext
     * @return array{success: bool, queued: bool, message: string, imported_files: int, expected_files: int, total_rows: int, persisted_rows: int, skipped_rows: int, duration_seconds: float, file_errors: array<int, string>, import_warnings: array<int, string>}
     */
    private function importFromPreview(array $preview, array $importContext = []): array
    {
        $startedAt = microtime(true);

        $entriesToImport = collect($preview)
            ->filter(fn (array $entry): bool => (bool) ($entry['can_import'] ?? false))
            ->filter(fn (array $entry): bool => isset($entry['file_path']) && is_string($entry['file_path']) && $entry['file_path'] !== '')
            ->values();

        if ($entriesToImport->isEmpty()) {
            return [
                'success' => false,
                'queued' => false,
                'message' => 'No hay archivos validos para importar. Corrige los archivos e intenta nuevamente.',
                'imported_files' => 0,
                'expected_files' => 0,
                'total_rows' => 0,
                'persisted_rows' => 0,
                'skipped_rows' => 0,
                'duration_seconds' => round(microtime(true) - $startedAt, 1),
                'file_errors' => [],
                'import_warnings' => [],
            ];
        }

        $userId = auth()->id();
        $provision = (bool) ($importContext['provision_client_and_sections'] ?? false);
        $replace = (bool) ($importContext['replace_previous_import_same_filename'] ?? false);

        $dispatched = 0;
        foreach ($entriesToImport as $entry) {
            $filePath = (string) $entry['file_path'];
            $originalName = (string) ($entry['file_name'] ?? basename($filePath));

            ProcessStoredVisitImportJob::dispatch(
                $filePath,
                is_int($userId) ? $userId : null,
                $originalName,
                $provision,
                $replace,
                null,
            );
            $dispatched++;
        }

        $expected = $entriesToImport->count();
        $previewRowTotal = (int) $entriesToImport->sum(fn (array $e): int => (int) ($e['total_rows'] ?? 0));

        if ($dispatched === 1) {
            $body = 'Tu archivo se recibió y lo estamos procesando. En unos minutos podrás ver el resultado en Importaciones de visitas.';
        } else {
            $body = 'Se recibieron '.$dispatched.' archivos y los estamos procesando. En unos minutos podrás ver los resultados en Importaciones de visitas.';
        }

        if ($previewRowTotal > 0) {
            $body .= $dispatched === 1
                ? ' Al revisar el archivo vimos '.$previewRowTotal.' filas con datos para importar.'
                : ' Al revisar los archivos vimos '.$previewRowTotal.' filas con datos para importar en total.';
        }

        return [
            'success' => true,
            'queued' => true,
            'message' => $body,
            'imported_files' => $dispatched,
            'expected_files' => $expected,
            'total_rows' => $previewRowTotal,
            'persisted_rows' => 0,
            'skipped_rows' => 0,
            'duration_seconds' => round(microtime(true) - $startedAt, 1),
            'file_errors' => [],
            'import_warnings' => [],
        ];
    }

    /**
     * @param  array<int, mixed>  $files
     * @param  array{provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     * @return array<int, array{file_name: string, file_path: string, can_import: bool, total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>}>
     */
    private function verifyUploadedFiles(array $files, array $context = []): array
    {
        return app(VisitImportFileHelper::class)->verifyFiles($files, $context);
    }

    /**
     * @param  array{provision_client_and_sections?: bool, replace_previous_import_same_filename?: bool}  $context
     */
    private function refreshVisitImportPreviewFromUploadedFiles(Get $get, Set $set): void
    {
        $files = (array) ($get('files') ?? []);

        if ($files === []) {
            $set('preview_payload', '[]');
            $set('import_result_payload', self::IMPORT_RESULT_PENDING_PAYLOAD);

            return;
        }

        $preview = app(VisitImportFileHelper::class)->verifyFiles(
            $files,
            $this->visitImportVerificationContextFromGet($get),
        );
        $set('preview_payload', json_encode($preview) ?: '[]');
        $set('import_result_payload', self::IMPORT_RESULT_PENDING_PAYLOAD);
    }

    /**
     * @return array{provision_client_and_sections: bool, replace_previous_import_same_filename: bool}
     */
    private function visitImportVerificationContextFromGet(Get $get): array
    {
        return [
            'provision_client_and_sections' => (bool) $get('provision_client_and_sections'),
            'replace_previous_import_same_filename' => (bool) $get('replace_previous_import_same_filename'),
        ];
    }

    /**
     * @return array<int, array{file_name: string, file_path: string, can_import: bool, total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>}>
     */
    private function decodePreviewPayload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->values()
            ->all();
    }

    private function renderProvisionClientHints(Get $get): string
    {
        if (! (bool) $get('provision_client_and_sections')) {
            return '';
        }

        $files = (array) ($get('files') ?? []);

        if ($files === []) {
            return '<p class="m-0 text-sm text-gray-600 dark:text-gray-300">Sube al menos un archivo para ver como se interpretara el nombre del cliente desde el archivo.</p>';
        }

        $rows = app(VisitImportFileHelper::class)->compactClientProvisioningHintsForFiles($files);

        return view('filament.pages.import-visit-reports.provision-hints', [
            'rows' => $rows,
        ])->render();
    }

    private function renderPreviewSummary(string $previewPayload): string
    {
        $preview = $this->decodePreviewPayload($previewPayload);

        if ($preview === []) {
            return '<p class="m-0 text-sm text-gray-600 dark:text-gray-300">Aun no se analizaron archivos.</p>';
        }

        return view('filament.pages.import-visit-reports.preview-items', [
            'preview' => $preview,
        ])->render();
    }

    /**
     * @return array{processed: bool, success: bool, queued: bool, message: string, imported_files: int, expected_files: int, total_rows: int, persisted_rows: int, skipped_rows: int, duration_seconds: float, file_errors: array<int, string>, import_warnings: array<int, string>}
     */
    private function decodeImportResultPayload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return [
                'processed' => false,
                'success' => false,
                'queued' => false,
                'message' => 'No se encontro el resultado de la importacion.',
                'imported_files' => 0,
                'expected_files' => 0,
                'total_rows' => 0,
                'persisted_rows' => 0,
                'skipped_rows' => 0,
                'duration_seconds' => 0.0,
                'file_errors' => [],
                'import_warnings' => [],
            ];
        }

        return [
            'processed' => (bool) ($decoded['processed'] ?? false),
            'success' => (bool) ($decoded['success'] ?? false),
            'queued' => (bool) ($decoded['queued'] ?? false),
            'message' => (string) ($decoded['message'] ?? ''),
            'imported_files' => (int) ($decoded['imported_files'] ?? 0),
            'expected_files' => (int) ($decoded['expected_files'] ?? 0),
            'total_rows' => (int) ($decoded['total_rows'] ?? 0),
            'persisted_rows' => (int) ($decoded['persisted_rows'] ?? 0),
            'skipped_rows' => (int) ($decoded['skipped_rows'] ?? 0),
            'duration_seconds' => (float) ($decoded['duration_seconds'] ?? 0),
            'file_errors' => collect($decoded['file_errors'] ?? [])->map(fn (mixed $error): string => (string) $error)->values()->all(),
            'import_warnings' => collect($decoded['import_warnings'] ?? [])->map(fn (mixed $warning): string => (string) $warning)->values()->all(),
        ];
    }

    private function renderProcessOrCompletedSummary(string $importResultPayload): string
    {
        $result = $this->decodeImportResultPayload($importResultPayload);

        if (! $result['processed']) {
            return view('filament.pages.import-visit-reports.process-step', [
                'canProcessImport' => $this->canProcessImport(),
            ])->render();
        }

        return $this->renderImportCompletedSummary($importResultPayload);
    }

    private function renderImportCompletedSummary(string $importResultPayload): string
    {
        $result = $this->decodeImportResultPayload($importResultPayload);

        return view('filament.pages.import-visit-reports.completed-summary', [
            'result' => $result,
            'finishUrl' => static::getUrl(),
            'listVisitsUrl' => VisitResource::getUrl('index'),
            'visitImportsUrl' => VisitImportResource::getUrl('index'),
        ])->render();
    }

    private function createWizardSubmitAction(): Htmlable
    {
        return new class($this) implements Htmlable
        {
            public function __construct(private ImportVisitReports $page) {}

            public function toHtml(): string
            {
                if ($this->page->isImportProcessed()) {
                    return '';
                }

                return view('filament.pages.import-visit-reports.wizard-submit-button', [
                    'canProcessImport' => $this->page->canProcessImport(),
                ])->render();
            }
        };
    }
}
