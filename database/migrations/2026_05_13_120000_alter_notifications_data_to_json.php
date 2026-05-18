<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filament filtra con `where('data->format', 'filament')`, que en PostgreSQL
     * requiere json/jsonb, no text. Documentación oficial:
     * https://filamentphp.com/docs/5.x/notifications/database-notifications
     * (misma nota en 4.x: usar `$table->json('data')` con PostgreSQL.)
     */
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'select udt_name from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
                ['notifications', 'data'],
            );

            if ($row !== null && strtolower((string) $row->udt_name) === 'text') {
                DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
            }

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $row = DB::selectOne(
                'select data_type from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
                ['notifications', 'data'],
            );

            if ($row !== null && strtolower((string) $row->data_type) === 'text') {
                DB::statement('ALTER TABLE notifications MODIFY data JSON NOT NULL');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'select udt_name from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
                ['notifications', 'data'],
            );

            if ($row !== null && strtolower((string) $row->udt_name) === 'jsonb') {
                DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
            }

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $row = DB::selectOne(
                'select data_type from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
                ['notifications', 'data'],
            );

            if ($row !== null && strtolower((string) $row->data_type) === 'json') {
                DB::statement('ALTER TABLE notifications MODIFY data TEXT NOT NULL');
            }
        }
    }
};
