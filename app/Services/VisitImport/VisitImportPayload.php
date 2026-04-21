<?php

namespace App\Services\VisitImport;

use Illuminate\Support\Collection;

class VisitImportPayload
{
    /**
     * @param  array<int, string>  $headers
     * @param  Collection<int, array<int, string>>  $rows
     * @param  array<int, int>|null  $rowGroups
     */
    public function __construct(
        public array $headers,
        public Collection $rows,
        public ?array $rowGroups = null,
    ) {
        if (is_array($this->rowGroups)) {
            $this->rowGroups = array_values($this->rowGroups);

            return;
        }

        $this->rowGroups = range(0, max($this->rows->count() - 1, 0));
    }

    public function rowGroupAt(int $rowIndex): int
    {
        return (int) ($this->rowGroups[$rowIndex] ?? $rowIndex);
    }

    public function totalSourceRows(): int
    {
        return count(array_unique($this->rowGroups ?? []));
    }
}
