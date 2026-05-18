<?php

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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('visit_reports', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);

            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();
        });

        Schema::table('visit_imports', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('visit_imports', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();
        });

        Schema::table('visit_reports', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);

            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();
        });
    }
};
