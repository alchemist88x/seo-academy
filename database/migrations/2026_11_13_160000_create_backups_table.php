<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backups')) {
            Schema::create('backups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('filename');
                $table->string('disk')->default('private_backups');
                $table->string('type')->default('database'); // database, files, full
                $table->string('database_driver')->default('sqlite'); // sqlite, mysql
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('status')->default('completed'); // completed, failed, in_progress
                $table->string('checksum')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->index(['created_at', 'status']);
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
