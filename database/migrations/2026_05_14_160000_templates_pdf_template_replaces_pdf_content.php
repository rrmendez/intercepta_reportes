<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->longText('pdf_template')->nullable()->after('content');
        });

        $path = resource_path('pdf-report-templates/default.blade.txt');
        $default = is_readable($path)
            ? (string) file_get_contents($path)
            : '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body><p>{{ $client->name }}</p></body></html>';

        DB::table('templates')->update(['pdf_template' => $default]);

        Schema::table('templates', function (Blueprint $table): void {
            $table->dropColumn('pdf_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->json('pdf_content')->nullable()->after('content');
        });

        DB::table('templates')->update(['pdf_content' => null]);

        Schema::table('templates', function (Blueprint $table): void {
            $table->dropColumn('pdf_template');
        });
    }
};
