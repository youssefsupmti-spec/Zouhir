<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aligne le schéma des zones et villes d'OUAISS EXPRESS sur le standard WinningExpress.
     */
    public function up(): void
    {
        // 1. Table zones (zones logistiques et tarifaires)
        if (! Schema::hasTable('zones')) {
            Schema::create('zones', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Table villes (référentiel national des villes du Maroc)
        if (! Schema::hasTable('villes')) {
            Schema::create('villes', function (Blueprint $table) {
                $table->id();
                $table->string('name_fr')->index();
                $table->string('name_ar')->nullable();
                $table->foreignId('zone_id')
                    ->nullable()
                    ->constrained('zones')
                    ->nullOnDelete();
                $table->string('code_zone', 50)->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('villes');
        Schema::dropIfExists('zones');
    }
};
