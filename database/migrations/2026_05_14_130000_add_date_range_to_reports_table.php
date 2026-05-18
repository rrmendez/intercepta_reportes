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
        Schema::table('reports', function (Blueprint $table) {
            $table->date('date_from')->nullable()->after('year');
            $table->date('date_until')->nullable()->after('date_from');
        });

        DB::table('reports')
            ->select(['id', 'month', 'year'])
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

        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'month', 'year']);
            $table->unique(['client_id', 'date_from', 'date_until']);
            $table->index(['date_from', 'date_until']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'date_from', 'date_until']);
            $table->dropIndex(['date_from', 'date_until']);
            $table->unique(['client_id', 'month', 'year']);
            $table->dropColumn(['date_from', 'date_until']);
        });
    }
};
