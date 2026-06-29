<?php

declare(strict_types=1);

namespace App\Services\Reports;

/**
 * Reemplaza texto seleccionado en la vista previa dentro del Blade source del reporte.
 *
 * El texto del preview puede provenir de literales HTML, de defaults dentro de bloques
 * `@php ... @endphp`, o de datos dinámicos (variables). Solo los dos primeros son editables
 * mediante reemplazo de cadena; el resto se reporta como `dynamic`.
 */
final class InlineTemplateTextEditor
{
    private const int MIN_SELECTION_LENGTH = 1;

    /**
     * Ventana de source (en bytes) que se inspecciona a cada lado de un match al puntuar el contexto.
     */
    private const int CONTEXT_WINDOW = 400;

    /**
     * Caracteres de contexto que deben coincidir para aceptar un match corto (<= 3 chars) o para
     * desempatar entre varias ocurrencias. Evita reemplazar dentro de CSS u otros literales.
     */
    private const int MIN_ANCHOR_SHORT = 4;

    private const int MIN_ANCHOR_MULTI = 3;

    private const int SHORT_SELECTION_LENGTH = 3;

    /**
     * @return array{ok: bool, source: string, reason: ?string}
     */
    public function replace(
        string $source,
        string $original,
        string $replacement,
        string $before = '',
        string $after = '',
    ): array {
        $trimmedOriginal = $this->normalize($original);

        if (mb_strlen($trimmedOriginal) < self::MIN_SELECTION_LENGTH) {
            return $this->failure($source, 'too_short');
        }

        $matches = $this->findFlexibleMatches($source, $trimmedOriginal);

        if ($matches === []) {
            return $this->failure($source, 'dynamic');
        }

        $match = $this->locate($source, $matches, $trimmedOriginal, $before, $after);

        if ($match === null) {
            $hasContext = $this->normalize($before) !== '' || $this->normalize($after) !== '';

            // Con contexto pero sin anclar a ningun literal: el texto proviene de datos dinamicos.
            // Sin contexto y con varias ocurrencias: no se pudo ubicar, se pide ampliar la seleccion.
            $reason = ($hasContext || count($matches) === 1) ? 'dynamic' : 'ambiguous';

            return $this->failure($source, $reason);
        }

        [$offset, $length] = $match;

        $escaped = $this->escapeReplacement($source, $offset, $replacement);

        $newSource = substr_replace($source, $escaped, $offset, $length);

        return [
            'ok' => true,
            'source' => $newSource,
            'reason' => null,
        ];
    }

    /**
     * Encuentra las ocurrencias del texto tolerando diferencias de espacios en blanco
     * (el DOM colapsa los espacios respecto al source).
     *
     * @return list<array{0: int, 1: int}> Pares [offset en bytes, longitud en bytes].
     */
    private function findFlexibleMatches(string $source, string $original): array
    {
        $pattern = $this->buildFlexiblePattern($original);

        if ($pattern === '') {
            return [];
        }

        if (preg_match_all('/'.$pattern.'/u', $source, $found, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $matches = [];

        foreach ($found[0] as $occurrence) {
            $matches[] = [(int) $occurrence[1], strlen($occurrence[0])];
        }

        return $matches;
    }

    private function buildFlexiblePattern(string $text): string
    {
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        $parts = array_filter($parts, static fn (string $part): bool => $part !== '');

        if ($parts === []) {
            return '';
        }

        return implode('\s+', array_map(static fn (string $part): string => preg_quote($part, '/'), $parts));
    }

    /**
     * Selecciona la ocurrencia exacta usando el contexto capturado en el momento de la selección.
     * Puntua cada match por cuantos caracteres del contexto del bloque coinciden, desde el borde
     * de la seleccion hacia afuera, comparando contra el source sin tags ni directivas Blade.
     *
     * @param  list<array{0: int, 1: int}>  $matches
     * @return array{0: int, 1: int}|null
     */
    private function locate(string $source, array $matches, string $original, string $before, string $after): ?array
    {
        $beforeNeedle = $this->normalize($before);
        $afterNeedle = $this->normalize($after);

        $isShort = mb_strlen($original) <= self::SHORT_SELECTION_LENGTH;

        if (count($matches) === 1) {
            $score = $this->contextScore($source, $matches[0], $beforeNeedle, $afterNeedle);

            if ($isShort && $score < self::MIN_ANCHOR_SHORT) {
                return null;
            }

            return $matches[0];
        }

        $bestScore = -1;
        $best = null;
        $tieCount = 0;

        foreach ($matches as $match) {
            $score = $this->contextScore($source, $match, $beforeNeedle, $afterNeedle);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $match;
                $tieCount = 1;
            } elseif ($score === $bestScore) {
                $tieCount++;
            }
        }

        if ($best === null || $bestScore < self::MIN_ANCHOR_MULTI) {
            return null;
        }

        // Varias ocurrencias con contexto identicamente fuerte: son intercambiables, se toma la primera.
        return $best;
    }

    /**
     * Numero de caracteres de contexto que coinciden a cada lado del match.
     *
     * @param  array{0: int, 1: int}  $match
     */
    private function contextScore(string $source, array $match, string $beforeNeedle, string $afterNeedle): int
    {
        [$offset, $length] = $match;

        $preceding = $this->stripToText(substr($source, max(0, $offset - self::CONTEXT_WINDOW), min($offset, self::CONTEXT_WINDOW)));
        $following = $this->stripToText(substr($source, $offset + $length, self::CONTEXT_WINDOW));

        return $this->commonSuffixLength($preceding, $beforeNeedle)
            + $this->commonPrefixLength($following, $afterNeedle);
    }

    private function commonSuffixLength(string $a, string $b): int
    {
        $a = array_reverse(mb_str_split($a));
        $b = array_reverse(mb_str_split($b));

        return $this->commonRunLength($a, $b);
    }

    private function commonPrefixLength(string $a, string $b): int
    {
        return $this->commonRunLength(mb_str_split($a), mb_str_split($b));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function commonRunLength(array $a, array $b): int
    {
        $max = min(count($a), count($b));
        $matched = 0;

        for ($i = 0; $i < $max; $i++) {
            if ($a[$i] !== $b[$i]) {
                break;
            }

            $matched++;
        }

        return $matched;
    }

    /**
     * Aproxima el texto renderizado de un fragmento de source quitando tags HTML, expresiones
     * `{{ }}` / `{!! !!}` y directivas Blade, para poder compararlo con el contexto del DOM.
     */
    private function stripToText(string $fragment): string
    {
        $fragment = (string) preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', $fragment);
        $fragment = (string) preg_replace('/@\w+\s*(\([^()]*\))?/', '', $fragment);
        $fragment = (string) preg_replace('/<[^>]*>/s', '', $fragment);

        return $this->normalize($fragment);
    }

    /**
     * Escapa el reemplazo según el contexto del match: dentro de un bloque `@php` se trata como
     * cadena PHP entre comillas simples; en markup HTML se neutralizan los caracteres especiales.
     */
    private function escapeReplacement(string $source, int $offset, string $replacement): string
    {
        if ($this->isInsidePhpBlock($source, $offset)) {
            return str_replace(['\\', "'"], ['\\\\', "\\'"], $replacement);
        }

        return htmlspecialchars($replacement, ENT_NOQUOTES, 'UTF-8');
    }

    private function isInsidePhpBlock(string $source, int $offset): bool
    {
        $before = substr($source, 0, $offset);

        $lastOpen = strripos($before, '@php');

        if ($lastOpen === false) {
            return false;
        }

        $lastClose = strripos($before, '@endphp');

        return $lastClose === false || $lastClose < $lastOpen;
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @return array{ok: bool, source: string, reason: string}
     */
    private function failure(string $source, string $reason): array
    {
        return [
            'ok' => false,
            'source' => $source,
            'reason' => $reason,
        ];
    }
}
