<?php

use App\VisitCountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_reports', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->nullable()->change();
            $table->string('count_status', 32)
                ->default(VisitCountStatus::Counted->value)
                ->after('quantity');
        });
    }

    public function down(): void
    {
        DB::table('visit_reports')->whereNull('quantity')->update(['quantity' => 0]);

        Schema::table('visit_reports', function (Blueprint $table): void {
            $table->dropColumn('count_status');
            $table->unsignedInteger('quantity')->nullable(false)->change();
        });
    }
};
