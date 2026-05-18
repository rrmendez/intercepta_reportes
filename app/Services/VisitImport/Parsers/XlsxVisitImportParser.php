<?php

namespace App\Services\VisitImport\Parsers;

use App\Services\VisitImport\Contracts\VisitImportParser;
use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class XlsxVisitImportParser implements VisitImportParser
{
    private const string SPREADSHEET_XML_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function parse(string $absolutePath): VisitImportPayload
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();

            throw new RuntimeException('Estructura XLSX invalida: falta la primera hoja.');
        }

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        $sharedStrings = $this->parseSharedStrings($sharedStringsXml ?: '');
        $rows = $this->parseRows($sheetXml, $sharedStrings);

        /** @var array<int, string> $headers */
        $headers = collect($rows[0] ?? [])
            ->map(fn (string $value): string => Str::of($value)->snake()->toString())
            ->all();

        $dataRows = collect(array_slice($rows, 1))
            ->map(fn (array $row): array => collect($row)->map(fn ($value): string => trim((string) $value))->all())
            ->filter(fn (array $row): bool => collect($row)->some(fn (string $value): bool => $value !== ''))
            ->values();

        return new VisitImportPayload($headers, $dataRows);
    }

    /**
     * @return array<int, string>
     */
    private function parseSharedStrings(string $sharedStringsXml): array
    {
        if ($sharedStringsXml === '') {
            return [];
        }

        $xml = simplexml_load_string($sharedStringsXml);

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $nodes = $this->xpathWithSpreadsheetNamespace($xml, '//x:si');

        if ($nodes === []) {
            return [];
        }

        return collect($nodes)
            ->map(function (SimpleXMLElement $node): string {
                $textNodes = $this->xpathWithSpreadsheetNamespace($node, './/x:t');

                if ($textNodes === []) {
                    return '';
                }

                return collect($textNodes)
                    ->map(fn (SimpleXMLElement $textNode): string => (string) $textNode)
                    ->implode('');
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function parseRows(string $sheetXml, array $sharedStrings): array
    {
        $xml = simplexml_load_string($sheetXml);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('XML de hoja XLSX invalido.');
        }

        $rowNodes = $this->xpathWithSpreadsheetNamespace($xml, '//x:sheetData/x:row');

        if ($rowNodes === []) {
            return [];
        }

        return collect($rowNodes)
            ->map(function (SimpleXMLElement $rowNode) use ($sharedStrings): array {
                $rowData = [];
                $cells = $this->xpathWithSpreadsheetNamespace($rowNode, './x:c');

                foreach ($cells as $cell) {
                    $reference = (string) $cell['r'];
                    $columnIndex = $this->columnIndexFromReference($reference);
                    $type = (string) $cell['t'];

                    $value = '';

                    if ($type === 's') {
                        $index = (int) ($cell->v ?? 0);
                        $value = $sharedStrings[$index] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $inlineText = $this->xpathWithSpreadsheetNamespace($cell, './x:is/x:t');
                        $value = (string) ($inlineText[0] ?? '');
                    } else {
                        $value = (string) ($cell->v ?? '');
                    }

                    $rowData[$columnIndex] = $value;
                }

                ksort($rowData);

                return array_values($rowData);
            })
            ->all();
    }

    /**
     * @return array<int, SimpleXMLElement>
     */
    private function xpathWithSpreadsheetNamespace(SimpleXMLElement $node, string $expression): array
    {
        $node->registerXPathNamespace('x', self::SPREADSHEET_XML_NAMESPACE);
        $matches = $node->xpath($expression);

        if ($matches === false) {
            return [];
        }

        return collect($matches)
            ->filter(fn (mixed $match): bool => $match instanceof SimpleXMLElement)
            ->values()
            ->all();
    }

    private function columnIndexFromReference(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?: 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }
}
