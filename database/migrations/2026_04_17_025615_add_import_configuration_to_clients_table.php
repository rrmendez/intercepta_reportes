<?php

use App\ClientImportMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('import_mode')
                ->default(ClientImportMode::SingleSectorSingleBird->value)
                ->after('notes');
            $table->string('default_location_name')
                ->default('Conteo')
                ->after('import_mode');
            $table->foreignId('default_bird_type_id')
                ->nullable()
                ->after('default_location_name')
                ->constrained('bird_types')
                ->nullOnDelete();

            $table->index('import_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropForeign(['default_bird_type_id']);
            $table->dropIndex(['import_mode']);
            $table->dropColumn([
                'import_mode',
                'default_location_name',
                'default_bird_type_id',
            ]);
        });
    }
};
