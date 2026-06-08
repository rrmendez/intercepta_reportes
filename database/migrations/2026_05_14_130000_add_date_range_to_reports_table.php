<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reports', 'date_from')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->date('date_from')->nullable()->after('year');
                $table->date('date_until')->nullable()->after('date_from');
            });
        }

        DB::table('reports')
            ->select(['id', 'month', 'year'])
            ->whereNull('date_from')
            ->orderBy('id')
            ->get()
            ->each(function (object $report): void {
                $periodStart = CarbonImmutable::create((int) $report->year, (int) $report->month, 1);

                DB::table('reports')
                    ->where('id', $report->id)
                    ->update([
                        'date_from' => $periodStart->toDateString(),
                        'date_until' => $periodStart->endOfMonth()->toDateString(),
                    ]);
            });

        if ($this->indexExists('reports', 'reports_client_id_month_year_unique')) {
            Schema::table('reports', function (Blueprint $table) {
                // MySQL reuses the composite unique index to back the client_id FK.
                $table->dropForeign(['client_id']);
                $table->dropUnique(['client_id', 'month', 'year']);
                $table->unique(['client_id', 'date_from', 'date_until']);
                $table->index(['date_from', 'date_until']);
                $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::getConnection()
            ->getSchemaBuilder()
            ->getIndexListing($table);

        return in_array($index, $indexes, true);
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropUnique(['client_id', 'date_from', 'date_until']);
            $table->dropIndex(['date_from', 'date_until']);
            $table->unique(['client_id', 'month', 'year']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->dropColumn(['date_from', 'date_until']);
        });
    }
};
