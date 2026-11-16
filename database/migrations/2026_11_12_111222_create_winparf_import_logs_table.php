<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('winparf_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('running'); // running, success, failed
            $table->integer('exit_code')->nullable();
            $table->text('files')->nullable();
            $table->longText('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('winparf_import_logs');
    }
};
