<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->foreignId('generated_by_user_id')
                ->nullable()
                ->after('client_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('email_sent_at')->nullable()->after('generated_at');
        });

        DB::table('reports')
            ->select(['id', 'data'])
            ->whereNotNull('data')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $data = json_decode((string) $row->data, true);
                    if (! is_array($data) || empty($data['email_sent_at'])) {
                        continue;
                    }

                    $parsed = CarbonImmutable::parse((string) $data['email_sent_at']);

                    DB::table('reports')
                        ->where('id', $row->id)
                        ->update(['email_sent_at' => $parsed]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropForeign(['generated_by_user_id']);
            $table->dropColumn(['generated_by_user_id', 'email_sent_at']);
        });
    }
};
