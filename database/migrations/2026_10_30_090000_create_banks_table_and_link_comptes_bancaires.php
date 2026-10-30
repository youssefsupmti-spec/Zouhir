<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Crée le référentiel des banques marocaines (banks) et lie comptes_bancaires par clé étrangère.
     */
    public function up(): void
    {
        // 1. Table des Banques (Catalogue National Bank Al-Maghrib)
        if (! Schema::hasTable('banks')) {
            Schema::create('banks', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code_etablissement', 10)->nullable()->index()->comment('Code RIB 3 chiffres');
                $table->string('swift_code', 20)->nullable()->index();
                $table->string('logo')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Contrainte de clé étrangère sur comptes_bancaires
        if (Schema::hasTable('comptes_bancaires')) {
            // Nettoyage préventif des orphelins (bank_id invalide) si besoin
            if (Schema::hasColumn('comptes_bancaires', 'bank_id')) {
                DB::table('comptes_bancaires')
                    ->whereNotNull('bank_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('banks')
                            ->whereColumn('banks.id', 'comptes_bancaires.bank_id');
                    })
                    ->update(['bank_id' => null]);
            }

            // Ajout de la contrainte FK
            if (DB::getDriverName() !== 'sqlite') {
                try {
                    Schema::table('comptes_bancaires', function (Blueprint $table) {
                        $table->foreign('bank_id')
                            ->references('id')
                            ->on('banks')
                            ->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // Contrainte potentiellement déjà présente
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('comptes_bancaires') && DB::getDriverName() !== 'sqlite') {
            try {
                Schema::table('comptes_bancaires', function (Blueprint $table) {
                    $table->dropForeign(['bank_id']);
                });
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        Schema::dropIfExists('banks');
    }
};
