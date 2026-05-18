<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropForeign(['default_bird_type_id']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['default_location_name', 'default_bird_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('default_location_name')
                ->default('Conteo')
                ->after('import_mode');
            $table->foreignId('default_bird_type_id')
                ->nullable()
                ->after('default_location_name')
                ->constrained('bird_types')
                ->nullOnDelete();
        });
    }
};
