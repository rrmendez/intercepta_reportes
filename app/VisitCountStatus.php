<?php

namespace App;

/**
 * Estado de un conteo dentro de una visita.
 *
 * `NotPerformed` distingue "no se pudo contar" (local cerrado, sin acceso) de un
 * conteo real de cero aves. Se representa con `quantity = null`.
 */
enum VisitCountStatus: string
{
    case Counted = 'counted';
    case NotPerformed = 'not_performed';

    /**
     * Valor centinela usado en las planillas de importacion para `NotPerformed`.
     */
    public const int NOT_PERFORMED_SENTINEL = -1;

    public function label(): string
    {
        return match ($this) {
            self::Counted => 'Contado',
            self::NotPerformed => 'Sin conteo',
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
