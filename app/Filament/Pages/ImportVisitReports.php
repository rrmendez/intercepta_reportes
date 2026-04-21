<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Visits\VisitResource;
use App\Services\ImportVisitExcelService;
use App\Services\VisitImport\Helpers\VisitImportFileHelper;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

class ImportVisitReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Business';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Import Visits';

    protected static ?string $title = 'Import Visits';

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
            'import_result_payload' => '{}',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('Upload files')
                        ->description('Upload one or more files.')
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->completedIcon(Heroicon::OutlinedCheck)
                        ->afterValidation(function (Get $get, Set $set): void {
                            $preview = $this->verifyUploadedFiles(
                                files: (array) ($get('files') ?? []),
                            );

                            $set('preview_payload', json_encode($preview) ?: '[]');
                            $set('import_result_payload', '{}');
                        })
                        ->schema([
                            FileUpload::make('files')
                                ->label('CSV / Excel files')
                                ->directory('imports/visit-reports')
                                ->disk('local')
                                ->visibility('private')
                                ->multiple()
                                ->acceptedFileTypes([
                                    'text/csv',
                                    'text/plain',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                ])
                                ->required(),
                            Hidden::make('preview_payload')
                                ->default('[]')
                                ->dehydrated(),
                            Hidden::make('import_result_payload')
                                ->default('{}')
                                ->dehydrated(),
                        ]),
                    Step::make('Validate files')
                        ->description('Review files with issues before importing.')
                        ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                        ->completedIcon(Heroicon::OutlinedCheck)
                        ->afterValidation(function (Get $get, Set $set): void {
                            $preview = $this->decodePreviewPayload((string) ($get('preview_payload') ?? '[]'));

                            if ($preview === []) {
                                $preview = $this->verifyUploadedFiles(
                                    files: (array) ($get('files') ?? []),
                                );

                                $set('preview_payload', json_encode($preview) ?: '[]');
                            }

                            $importResult = $this->importFromPreview(
                                preview: $preview,
                            );

                            $set('import_result_payload', json_encode($importResult) ?: '{}');

                            if (! $importResult['success']) {
                                Notification::make()
                                    ->title('Import failed')
                                    ->body((string) $importResult['message'])
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }

                            Notification::make()
                                ->title('Import completed')
                                ->body((string) $importResult['message'])
                                ->success()
                                ->send();
                        })
                        ->schema([
                            Placeholder::make('preview_results')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => new HtmlString(
                                    $this->renderPreviewSummary((string) $get('preview_payload')),
                                )),
                        ]),
                    Step::make('Confirm')
                        ->description('Import finished successfully.')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->completedIcon(Heroicon::OutlinedCheckCircle)
                        ->schema([
                            Placeholder::make('import_completed_summary')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => new HtmlString(
                                    $this->renderImportCompletedSummary((string) $get('import_result_payload')),
                                )),
                        ]),
                ])
                    ->nextAction(fn (Action $action): Action => $action->label('Next'))
                    ->previousAction(
                        fn (Action $action): Action => $action->extraAttributes([
                            'x-cloak' => 'x-cloak',
                            'x-show' => '! isFirstStep() && ! isLastStep()',
                        ]),
                    ),
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
     * @return array{success: bool, message: string, imported_files: int, expected_files: int, total_rows: int, persisted_rows: int, skipped_rows: int, duration_seconds: float, file_errors: array<int, string>}
     */
    private function importFromPreview(array $preview): array
    {
        $startedAt = microtime(true);

        $filesToImport = collect($preview)
            ->filter(fn (array $entry): bool => (bool) ($entry['can_import'] ?? false))
            ->pluck('file_path')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        if ($filesToImport === []) {
            return [
                'success' => false,
                'message' => 'There are no valid files to import. Fix the files and try again.',
                'imported_files' => 0,
                'expected_files' => 0,
                'total_rows' => 0,
                'persisted_rows' => 0,
                'skipped_rows' => 0,
                'duration_seconds' => round(microtime(true) - $startedAt, 1),
                'file_errors' => [],
            ];
        }

        $importedFiles = 0;
        $totalRows = 0;
        $persistedRows = 0;
        $skippedRows = 0;
        $fileErrors = [];

        foreach ($filesToImport as $filePath) {
            try {
                $result = app(ImportVisitExcelService::class)->import(
                    Storage::disk('local')->path($filePath),
                );

                $importedFiles++;
                $totalRows += $result['total_rows'];
                $persistedRows += $result['persisted_rows'];
                $skippedRows += $result['skipped_rows'];
            } catch (ValidationException $exception) {
                $fileErrors[] = basename($filePath).': '.$this->firstErrorMessage($exception);
            } catch (Throwable $exception) {
                report($exception);
                $fileErrors[] = basename($filePath).': Unexpected error while importing file.';
            }
        }

        if ($importedFiles === 0) {
            return [
                'success' => false,
                'message' => collect($fileErrors)->take(3)->implode(' | '),
                'imported_files' => 0,
                'expected_files' => count($filesToImport),
                'total_rows' => 0,
                'persisted_rows' => 0,
                'skipped_rows' => 0,
                'duration_seconds' => round(microtime(true) - $startedAt, 1),
                'file_errors' => $fileErrors,
            ];
        }

        $body = "Files imported: {$importedFiles}/".count($filesToImport)
            ." | Rows: {$totalRows} | Persisted: {$persistedRows} | Skipped: {$skippedRows}";

        if ($fileErrors !== []) {
            $body .= ' | Errors: '.collect($fileErrors)->take(2)->implode(' | ');
        }

        return [
            'success' => true,
            'message' => $body,
            'imported_files' => $importedFiles,
            'expected_files' => count($filesToImport),
            'total_rows' => $totalRows,
            'persisted_rows' => $persistedRows,
            'skipped_rows' => $skippedRows,
            'duration_seconds' => round(microtime(true) - $startedAt, 1),
            'file_errors' => $fileErrors,
        ];
    }

    /**
     * @param  array<int, mixed>  $files
     * @return array<int, array{file_name: string, file_path: string, can_import: bool, total_rows: int, valid_rows: int, invalid_rows: int, errors: array<int, string>}>
     */
    private function verifyUploadedFiles(array $files): array
    {
        return app(VisitImportFileHelper::class)->verifyFiles($files);
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

    private function renderPreviewSummary(string $previewPayload): string
    {
        $preview = $this->decodePreviewPayload($previewPayload);

        if ($preview === []) {
            return '<p class="m-0 text-sm text-gray-600 dark:text-gray-300">No files were analyzed yet.</p>';
        }

        return view('filament.pages.import-visit-reports.preview-items', [
            'preview' => $preview,
        ])->render();
    }

    /**
     * @return array{success: bool, message: string, imported_files: int, expected_files: int, total_rows: int, persisted_rows: int, skipped_rows: int, duration_seconds: float, file_errors: array<int, string>}
     */
    private function decodeImportResultPayload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Import result not found.',
                'imported_files' => 0,
                'expected_files' => 0,
                'total_rows' => 0,
                'persisted_rows' => 0,
                'skipped_rows' => 0,
                'duration_seconds' => 0.0,
                'file_errors' => [],
            ];
        }

        return [
            'success' => (bool) ($decoded['success'] ?? false),
            'message' => (string) ($decoded['message'] ?? ''),
            'imported_files' => (int) ($decoded['imported_files'] ?? 0),
            'expected_files' => (int) ($decoded['expected_files'] ?? 0),
            'total_rows' => (int) ($decoded['total_rows'] ?? 0),
            'persisted_rows' => (int) ($decoded['persisted_rows'] ?? 0),
            'skipped_rows' => (int) ($decoded['skipped_rows'] ?? 0),
            'duration_seconds' => (float) ($decoded['duration_seconds'] ?? 0),
            'file_errors' => collect($decoded['file_errors'] ?? [])->map(fn (mixed $error): string => (string) $error)->values()->all(),
        ];
    }

    private function renderImportCompletedSummary(string $importResultPayload): string
    {
        $result = $this->decodeImportResultPayload($importResultPayload);

        return view('filament.pages.import-visit-reports.completed-summary', [
            'result' => $result,
            'finishUrl' => static::getUrl(),
            'listVisitsUrl' => VisitResource::getUrl('index'),
        ])->render();
    }

    private function firstErrorMessage(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first() ?? 'Validation failed.');
    }
}
