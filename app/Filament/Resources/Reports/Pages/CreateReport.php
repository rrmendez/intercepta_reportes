<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Client;
use App\Models\Report;
use App\Models\Template;
use App\Services\GenerateMonthlyReportPdfService;
use App\Services\ReportBladeStringRenderer;
use App\Services\ReportPdfTemplateDefaults;
use App\Services\Reports\ReportBladeVariableReference;
use App\Services\Reports\ReportPeriodData;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;

class CreateReport extends Page
{
    protected static string $resource = ReportResource::class;

    protected static ?string $title = 'Crear reporte';

    protected string $view = 'filament.resources.reports.pages.create-report';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $defaultStart = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $this->form->fill([
            'client_id' => request()->query('client_id'),
            'template_id' => request()->query('template_id'),
            'date_from' => request()->query('date_from', $defaultStart->toDateString()),
            'date_until' => request()->query('date_until', $defaultStart->endOfMonth()->toDateString()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return ReportResource::form($schema)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('report-create-form')
                    ->livewireSubmitHandler('generate'),
            ]);
    }

    public function generate(GenerateMonthlyReportPdfService $reports): mixed
    {
        $state = $this->form->getState();

        $report = $reports->generateForRange(
            clientId: (int) $state['client_id'],
            dateFrom: (string) $state['date_from'],
            dateUntil: (string) $state['date_until'],
            templateId: filled($state['template_id'] ?? null) ? (int) $state['template_id'] : null,
        );

        Notification::make()
            ->title('Reporte generado')
            ->body($report->generated_filename)
            ->success()
            ->send();

        return redirect()->to(ReportResource::getUrl('index'));
    }

    public function getPeriodPreview(): ?array
    {
        $client = $this->getSelectedClient();
        $dateFrom = $this->data['date_from'] ?? null;
        $dateUntil = $this->data['date_until'] ?? null;

        if ($client === null || blank($dateFrom) || blank($dateUntil)) {
            return null;
        }

        try {
            return app(ReportPeriodData::class)->load($client, (string) $dateFrom, (string) $dateUntil);
        } catch (Throwable) {
            return null;
        }
    }

    public function getTemplatePreviewHtml(): Htmlable
    {
        $period = $this->getPeriodPreview();
        $client = $this->getSelectedClient();

        if ($period === null || $client === null) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Selecciona un cliente y un rango valido para previsualizar la plantilla.</p>');
        }

        $template = $this->getSelectedTemplate($client);
        $blade = $template?->pdf_template ?? ReportPdfTemplateDefaults::bladeSourceForClient($client);

        $report = Report::make([
            'client_id' => $client->id,
            'date_from' => $period['date_from']->toDateString(),
            'date_until' => $period['date_until']->toDateString(),
            'generated_at' => now(),
        ])->setRelation('client', $client);

        $renderer = app(ReportBladeStringRenderer::class);
        $result = $renderer->tryRenderDocument($blade, $client, $report, $period);

        if (! $result['ok']) {
            return new HtmlString(
                '<div x-ignore class="max-h-[70vh] overflow-auto rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">'
                .'<p class="text-sm text-gray-500 dark:text-gray-400">'.e((string) ($result['message'] ?? 'Error en la plantilla.')).'</p>'
                .'</div>',
            );
        }

        $html = (string) $result['html'];

        if (blank($html)) {
            $html = '<p class="text-sm text-gray-500 dark:text-gray-400">La previsualizacion aparecera cuando la plantilla tenga contenido.</p>';
        }

        $safeHtml = $renderer->htmlForAdminPreview($html);

        return new HtmlString(
            '<div x-ignore class="max-h-[70vh] overflow-auto rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">'
            .$safeHtml
            .'</div>',
        );
    }

    public function getTemplateVariablesHtml(): Htmlable
    {
        $period = $this->getPeriodPreview();
        $client = $this->getSelectedClient();

        if ($period === null || $client === null) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Selecciona un cliente y un rango valido para ver las variables.</p>');
        }

        $report = Report::make([
            'client_id' => $client->id,
            'date_from' => $period['date_from']->toDateString(),
            'date_until' => $period['date_until']->toDateString(),
            'generated_at' => now(),
        ])->setRelation('client', $client);

        $bladeData = app(ReportBladeStringRenderer::class)->bladeData($client, $report, $period);

        $note = '$visits es un array de filas con las mismas claves que la tabla de arriba; incluye las visitas del cliente en el rango de fechas del formulario.';

        return app(ReportBladeVariableReference::class)->toHtml($bladeData, $note);
    }

    private function getSelectedClient(): ?Client
    {
        $clientId = $this->data['client_id'] ?? null;

        if (! filled($clientId)) {
            return null;
        }

        return Client::query()->find((int) $clientId);
    }

    private function getSelectedTemplate(Client $client): ?Template
    {
        $templateId = $this->data['template_id'] ?? null;

        if (filled($templateId)) {
            return Template::query()
                ->whereBelongsTo($client)
                ->find((int) $templateId);
        }

        return $client->templates()
            ->where('active', true)
            ->latest('id')
            ->first();
    }
}
