<?php

namespace App\Services\HistoricVisitImport;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

final class HistoricVisitSpreadsheetReader
{
    public const int HISTORIC_SHEET_INDEX = 2;

    private const int MIN_QUANTITY_COLUMN_ROWS = 5;

    /**
     * @return array{
     *     rows: Collection<int, array{date: ?float, quantities: array<int, int>}>,
     *     section_column_indices: array<int, int>,
     *     column_headers: array<int, string|null>,
     *     has_header_row: bool,
     * }
     */
    public function read(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheetCount = $spreadsheet->getSheetCount();

        if ($sheetCount <= self::HISTORIC_SHEET_INDEX) {
            throw new RuntimeException(
                'Se esperaban al menos '.(self::HISTORIC_SHEET_INDEX + 1).' hojas; el archivo tiene '.$sheetCount.'.',
            );
        }

        $sheet = $spreadsheet->getSheet(self::HISTORIC_SHEET_INDEX);
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $highestRow = (int) $sheet->getHighestDataRow();

        if ($highestRow < 1 || $highestColumn < 2) {
            throw new RuntimeException('La hoja historica no contiene filas de datos.');
        }

        $hasHeaderRow = $this->hasHeaderRow($sheet, $highestColumn);
        $dataStartRow = $hasHeaderRow ? 2 : 1;
        $columnHeaders = $this->readColumnHeaders($sheet, $highestColumn, $hasHeaderRow);
        $sectionColumnIndices = $this->resolveQuantityColumnIndices(
            $sheet,
            $highestRow,
            $highestColumn,
            $dataStartRow,
            $columnHeaders,
        );

        if ($sectionColumnIndices === []) {
            throw new RuntimeException('No se encontraron columnas de cantidad en la hoja historica.');
        }

        $rows = collect();

        for ($rowIndex = $dataStartRow; $rowIndex <= $highestRow; $rowIndex++) {
            $dateValue = $this->cellRawValue($sheet, 1, $rowIndex);

            if (! $this->isNumericCellValue($dateValue)) {
                continue;
            }

            $quantities = [];

            foreach ($sectionColumnIndices as $columnIndex) {
                $rawQuantity = $this->cellRawValue($sheet, $columnIndex, $rowIndex);

                if (! $this->isQuantityCellValue($rawQuantity)) {
                    continue;
                }

                $quantities[$columnIndex] = (int) round((float) $rawQuantity);
            }

            if ($quantities === []) {
                continue;
            }

            $rows->push([
                'date' => (float) $dateValue,
                'quantities' => $quantities,
            ]);
        }

        if ($rows->isEmpty()) {
            throw new RuntimeException('La hoja historica no contiene visitas validas.');
        }

        return [
            'rows' => $rows,
            'section_column_indices' => $sectionColumnIndices,
            'column_headers' => $columnHeaders,
            'has_header_row' => $hasHeaderRow,
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function readColumnHeaders(Worksheet $sheet, int $highestColumn, bool $hasHeaderRow): array
    {
        if (! $hasHeaderRow) {
            return [];
        }

        $headers = [];

        for ($columnIndex = 1; $columnIndex <= $highestColumn; $columnIndex++) {
            $value = $this->cellRawValue($sheet, $columnIndex, 1);

            if (! is_string($value)) {
                $headers[$columnIndex] = null;

                continue;
            }

            $trimmed = trim($value);

            $headers[$columnIndex] = $trimmed === '' ? null : $trimmed;
        }

        return $headers;
    }

    private function hasHeaderRow(Worksheet $sheet, int $highestColumn): bool
    {
        $firstColumnValue = $this->cellRawValue($sheet, 1, 1);

        if (is_string($firstColumnValue) && ! $this->isNumericCellValue(trim($firstColumnValue))) {
            return true;
        }

        for ($columnIndex = 2; $columnIndex <= $highestColumn; $columnIndex++) {
            $value = $this->cellRawValue($sheet, $columnIndex, 1);

            if (is_string($value) && trim($value) !== '' && ! $this->isNumericCellValue(trim($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|null>  $columnHeaders
     * @return array<int, int>
     */
    private function resolveQuantityColumnIndices(
        Worksheet $sheet,
        int $highestRow,
        int $highestColumn,
        int $dataStartRow,
        array $columnHeaders,
    ): array {
        $dataRowCount = max($highestRow - $dataStartRow + 1, 0);
        $minimumRows = $dataRowCount <= self::MIN_QUANTITY_COLUMN_ROWS
            ? 1
            : max(self::MIN_QUANTITY_COLUMN_ROWS, (int) ceil($dataRowCount * 0.05));
        $headerDefinedColumns = [];

        foreach ($columnHeaders as $columnIndex => $header) {
            if ($columnIndex === 1 || ! is_string($header) || trim($header) === '') {
                continue;
            }

            if ($this->normalizeHeaderToken($header) === 'fecha') {
                continue;
            }

            $headerDefinedColumns[] = $columnIndex;
        }

        if ($headerDefinedColumns !== []) {
            return $headerDefinedColumns;
        }

        $indices = [];

        for ($columnIndex = 2; $columnIndex <= $highestColumn; $columnIndex++) {
            $quantityRows = 0;

            for ($rowIndex = $dataStartRow; $rowIndex <= $highestRow; $rowIndex++) {
                if ($this->isQuantityCellValue($this->cellRawValue($sheet, $columnIndex, $rowIndex))) {
                    $quantityRows++;
                }
            }

            if ($quantityRows >= $minimumRows) {
                $indices[] = $columnIndex;
            }
        }

        return $indices;
    }

    private function cellRawValue(Worksheet $sheet, int $columnIndex, int $rowIndex): mixed
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex).$rowIndex);

        return $cell->getCalculatedValue();
    }

    private function isNumericCellValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_numeric($value)) {
            return true;
        }

        return is_string($value) && is_numeric(trim($value));
    }

    private function isQuantityCellValue(mixed $value): bool
    {
        if (! $this->isNumericCellValue($value)) {
            return false;
        }

        $numeric = (float) $value;

        if ($numeric < 0) {
            return false;
        }

        return abs($numeric - round($numeric)) < 0.0001;
    }

    private function normalizeHeaderToken(string $value): string
    {
        return strtolower(trim($value));
    }

    public function excelSerialToDateString(float $serial, string $time): string
    {
        $dateTime = ExcelDate::excelToDateTimeObject($serial);

        return $dateTime->format('Y-m-d').' '.$time;
    }
}
