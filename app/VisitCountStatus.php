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

    /**
     * Marca alternativa al centinela numerico, aceptada en mayuscula o minuscula.
     */
    public const string NOT_PERFORMED_LETTER = 'x';

    /**
     * Indica si un valor cargado en una celda de cantidad marca un conteo no
     * realizado: el centinela `-1` o la letra `x`/`X`.
     */
    public static function isNotPerformedMarker(mixed $value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === self::NOT_PERFORMED_LETTER) {
            return true;
        }

        return is_numeric($normalized) && (float) $normalized === (float) self::NOT_PERFORMED_SENTINEL;
    }

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
