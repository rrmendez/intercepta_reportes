<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropUnique(['client_id', 'date_from', 'date_until']);
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->index(['client_id', 'date_from', 'date_until']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'date_from', 'date_until']);
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->unique(['client_id', 'date_from', 'date_until']);
        });
    }
};
