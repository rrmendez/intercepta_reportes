<?php

namespace App;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Generated => 'Generado',
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
