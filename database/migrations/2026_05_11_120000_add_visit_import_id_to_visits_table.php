<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->foreignId('visit_import_id')
                ->nullable()
                ->after('client_id')
                ->constrained('visit_imports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropForeign(['visit_import_id']);
            $table->dropColumn('visit_import_id');
        });
    }
};
