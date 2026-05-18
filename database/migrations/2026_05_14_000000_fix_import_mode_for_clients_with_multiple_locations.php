<?php

declare(strict_types=1);

use App\ClientImportMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clientes con más de una ubicación activa no encajan en el modo compacto
     * (una columna "Conteo"); alinear import_mode con el uso real.
     */
    public function up(): void
    {
        $from = ClientImportMode::SingleSectorSingleBird->value;
        $to = ClientImportMode::MultiSectorSingleBird->value;

        DB::table('clients')
            ->where('import_mode', $from)
            ->whereRaw(
                '(select count(*) from locations where locations.client_id = clients.id and locations.active = ?) > 1',
                [true],
            )
            ->update(['import_mode' => $to, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // No revertimos: no es posible saber qué clientes eran compacto real vs corregidos.
    }
};
