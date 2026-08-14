<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->unsignedSmallInteger('nests_removed')->nullable()->after('observation');
            $table->unsignedSmallInteger('captures_falconry')->nullable()->after('nests_removed');
            $table->unsignedSmallInteger('captures_trap')->nullable()->after('captures_falconry');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropColumn(['nests_removed', 'captures_falconry', 'captures_trap']);
        });
    }
};
