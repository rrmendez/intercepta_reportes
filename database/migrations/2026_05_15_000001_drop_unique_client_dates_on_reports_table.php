<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropUnique(['client_id', 'date_from', 'date_until']);
            $table->index(['client_id', 'date_from', 'date_until']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropIndex(['client_id', 'date_from', 'date_until']);
            $table->unique(['client_id', 'date_from', 'date_until']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }
};
