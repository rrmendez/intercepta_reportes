<?php

declare(strict_types=1);

namespace App\Services\VisitObservations;

use Illuminate\Support\Str;

final class VisitObservationMetricsExtractor
{
    /**
     * Tope de los campos smallint de la tabla visits.
     */
    public const MAX_METRIC_VALUE = 32767;

    /**
     * Cantidad en dígitos o en palabra (sobre texto ya normalizado: minúsculas, sin tildes).
     */
    private const NUMBER_PATTERN = '\d+|un[ao]?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|trece|catorce|quince|dieciseis|diecisiete|dieciocho|diecinueve|veinte';

    /**
     * @var array<string, int>
     */
    private const NUMBER_WORDS = [
        'un' => 1, 'una' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4,
        'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9,
        'diez' => 10, 'once' => 11, 'doce' => 12, 'trece' => 13, 'catorce' => 14,
        'quince' => 15, 'dieciseis' => 16, 'diecisiete' => 17, 'dieciocho' => 18,
        'diecinueve' => 19, 'veinte' => 20,
    ];

    /**
     * El relleno [^.\n\d]*? entre verbo y sustantivo excluye dígitos para que un número
     * intermedio caiga siempre en el grupo de captura ("retira 2 nidos") y a la vez
     * tolera palabras intermedias ("captura de una paloma"). NUM se reemplaza por
     * NUMBER_PATTERN al compilar.
     *
     * @var array<int, string>
     */
    private const CAPTURE_PATTERNS = [
        // "se captura una paloma", "se capturan 2 palomas", "se atraparon 4 palomas", "captura de dos palomas"
        '/\b(?:captur|atrap)[a-z]*[^.\n\d]*?(?:\b(NUM)\s+)?(?:palomas?|aves?|ejemplares?|pajaros?)\b/u',
        // "2 palomas capturadas", "paloma atrapada"
        '/\b(?:(NUM)\s+)?(?:palomas?|aves?|ejemplares?|pajaros?)\s+(?:capturad|atrapad)[a-z]*\b/u',
    ];

    /**
     * @var array<int, string>
     */
    private const NEST_PATTERNS = [
        // "se quita nido en Doblados", "Se retira 2 nidos", "se sacan 3 nidos", "se remueve nido", "se extraen 3 nidos"
        '/\b(?:retir|quit|sac|remov|remuev|extra)[a-z]*[^.\n\d]*?(?:\b(NUM)\s+)?nidos?\b/u',
        // "2 nidos retirados", "nido quitado", "nidos extraidos"
        '/\b(?:(NUM)\s+)?nidos?\s+(?:retirad|quitad|sacad|removid|extraid)[a-z]*\b/u',
    ];

    private const TRAP_PATTERN = '/\btrampas?\b/u';

    public function extract(?string $observation): VisitObservationMetrics
    {
        $normalized = Str::lower(Str::ascii(trim((string) $observation)));

        $nestsRemoved = 0;
        $capturesFalconry = 0;
        $capturesTrap = 0;

        foreach ($this->sentences($normalized) as $sentence) {
            $captures = $this->countMatches($sentence, self::CAPTURE_PATTERNS);

            if (preg_match(self::TRAP_PATTERN, $sentence) === 1) {
                $capturesTrap += $captures;
            } else {
                $capturesFalconry += $captures;
            }

            $nestsRemoved += $this->countMatches($sentence, self::NEST_PATTERNS);
        }

        return new VisitObservationMetrics(
            nestsRemoved: min($nestsRemoved, self::MAX_METRIC_VALUE),
            capturesFalconry: min($capturesFalconry, self::MAX_METRIC_VALUE),
            capturesTrap: min($capturesTrap, self::MAX_METRIC_VALUE),
        );
    }

    /**
     * @return array<int, string>
     */
    private function sentences(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $sentences = preg_split('/[\n.;]+/u', $normalized) ?: [];

        return array_values(array_filter(array_map('trim', $sentences)));
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function countMatches(string $sentence, array $patterns): int
    {
        $total = 0;

        foreach ($patterns as $pattern) {
            $compiled = str_replace('NUM', self::NUMBER_PATTERN, $pattern);

            if (preg_match_all($compiled, $sentence, $matches) > 0) {
                foreach ($matches[1] as $quantity) {
                    $total += $this->quantityToCount($quantity);
                }
            }
        }

        return $total;
    }

    private function quantityToCount(string $quantity): int
    {
        if ($quantity === '') {
            return 1;
        }

        if (ctype_digit($quantity)) {
            return (int) $quantity;
        }

        return self::NUMBER_WORDS[$quantity] ?? 1;
    }
}
