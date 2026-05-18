<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_file_path')->nullable();
            $table->string('summary_message', 1024)->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('persisted_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->string('import_status', 32)->default('success');
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_imports');
    }
};
