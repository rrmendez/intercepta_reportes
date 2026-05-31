<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Template;

final class ClientPdfTemplateService
{
    public function ensureActiveTemplate(Client $client): Template
    {
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

    public function saveActiveTemplate(Client $client, string $pdfTemplate): Template
    {
        $template = $this->ensureActiveTemplate($client);

        $template->update([
            'pdf_template' => $pdfTemplate,
            'active' => true,
        ]);

        return $template->fresh() ?? $template;
    }
}
