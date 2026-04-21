<?php

namespace App\Services\VisitImport\Parsers;

use App\Services\VisitImport\Contracts\VisitImportParser;
use App\Services\VisitImport\VisitImportPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileObject;

class CsvVisitImportParser implements VisitImportParser
{
    public function parse(string $absolutePath): VisitImportPayload
    {
        $file = new SplFileObject($absolutePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        /** @var array<int, string> $headers */
        $headers = [];
        $rows = collect();

        foreach ($file as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cleanRow = collect($row)
                ->map(fn (mixed $value): string => trim((string) $value))
                ->values()
                ->all();

            if (collect($cleanRow)->every(fn (string $value): bool => $value === '')) {
                continue;
            }

            if ($headers === []) {
                $headers = collect($cleanRow)
                    ->map(fn (string $value): string => Str::of($value)->snake()->toString())
                    ->all();

                continue;
            }

            /** @var Collection<int, string> $rowCollection */
            $rowCollection = collect($cleanRow);
            $rows->push($rowCollection->all());
        }

        return new VisitImportPayload($headers, $rows);
    }
}
