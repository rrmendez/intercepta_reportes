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

    /**
     * @return array{
     *     rows: Collection<int, array{date: ?float, quantities: array<int, int>}>,
     *     section_column_indices: array<int, int>,
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

        $sectionColumnIndices = $this->resolveSectionColumnIndices($sheet, $highestRow, $highestColumn);

        if ($sectionColumnIndices === []) {
            throw new RuntimeException('No se encontraron columnas de cantidad en la hoja historica.');
        }

        $rows = collect();

        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            $dateValue = $this->cellRawValue($sheet, 1, $rowIndex);

            if (! $this->isNumericCellValue($dateValue)) {
                continue;
            }

            $quantities = [];

            foreach ($sectionColumnIndices as $columnIndex) {
                $rawQuantity = $this->cellRawValue($sheet, $columnIndex, $rowIndex);

                if (! $this->isNumericCellValue($rawQuantity)) {
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
        ];
    }

    /**
     * @return array<int, int>
     */
    private function resolveSectionColumnIndices(Worksheet $sheet, int $highestRow, int $highestColumn): array
    {
        $indices = [];

        for ($columnIndex = 2; $columnIndex <= $highestColumn; $columnIndex++) {
            $hasData = false;

            for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
                if ($this->isNumericCellValue($this->cellRawValue($sheet, $columnIndex, $rowIndex))) {
                    $hasData = true;

                    break;
                }
            }

            if ($hasData) {
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

    public function excelSerialToDateString(float $serial, string $time): string
    {
        $dateTime = ExcelDate::excelToDateTimeObject($serial);

        return $dateTime->format('Y-m-d').' '.$time;
    }
}
