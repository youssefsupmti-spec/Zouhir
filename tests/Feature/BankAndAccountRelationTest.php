<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\UserRole;
use App\Models\Bank;
use App\Models\CompteBancaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAndAccountRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_creation_and_attributes(): void
    {
        $bank = Bank::create([
            'name' => 'CIH Bank',
            'code_etablissement' => '230',
            'swift_code' => 'CIHBMAMC',
            'status' => 'active',
        ]);

        $this->assertNotNull($bank->id);
        $this->assertEquals('CIH Bank', $bank->name);
        $this->assertEquals('230', $bank->code_etablissement);
        $this->assertEquals('CIHBMAMC', $bank->swift_code);
        $this->assertEquals('active', $bank->status);
        $this->assertCount(1, Bank::active()->get());
    }

    public function test_compte_bancaire_belongs_to_bank_and_user(): void
    {
        $user = User::factory()->create([
            'nom_complet' => 'Marchand Test',
            'role' => UserRole::CLIENT,
        ]);

        $bank = Bank::create([
            'name' => 'Attijariwafa Bank',
            'code_etablissement' => '007',
            'swift_code' => 'BCMAMAMC',
            'status' => 'active',
        ]);

        $compte = CompteBancaire::create([
            'owner_id' => $user->id,
            'owner_type' => CompteBancaire::OWNER_CLIENT,
            'titulaire_nom' => 'Marchand Test',
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'rib' => '007780000123456789012345',
            'is_default' => true,
        ]);

        $this->assertNotNull($compte->id);
        $this->assertEquals($bank->id, $compte->bank->id);
        $this->assertEquals('Attijariwafa Bank', $compte->bank->name);
        $this->assertEquals($user->id, $compte->owner->id);
        $this->assertCount(1, $bank->comptes);
    }

    public function test_backfill_banks_command_links_accounts_by_rib_and_alias(): void
    {
        $user = User::factory()->create([
            'nom_complet' => 'Vendeur Casablanca',
            'role' => UserRole::CLIENT,
        ]);

        // Compte 1: RIB commençant par 230 (CIH Bank) sans bank_id
        $compte1 = CompteBancaire::create([
            'owner_id' => $user->id,
            'owner_type' => CompteBancaire::OWNER_CLIENT,
            'titulaire_nom' => 'Vendeur Casablanca',
            'bank_name' => 'Inconnue',
            'rib' => '230780000123456789012345',
            'is_default' => true,
        ]);

        // Compte 2: RIB sans prefixe BAM direct mais bank_name 'BMCI'
        $compte2 = CompteBancaire::create([
            'owner_id' => $user->id,
            'owner_type' => CompteBancaire::OWNER_CLIENT,
            'titulaire_nom' => 'Vendeur Casablanca 2',
            'bank_name' => 'BMCI GROUPE',
            'rib' => '999999000123456789012345',
            'is_default' => false,
        ]);

        $this->assertNull($compte1->bank_id);
        $this->assertNull($compte2->bank_id);

        // Exécution de la commande Artisan
        $this->artisan('app:backfill-banks')
            ->assertExitCode(0);

        $compte1->refresh();
        $compte2->refresh();

        $this->assertNotNull($compte1->bank_id);
        $this->assertEquals('CIH Bank', $compte1->bank->name);

        $this->assertNotNull($compte2->bank_id);
        $this->assertEquals('BMCI (Groupe BNP Paribas)', $compte2->bank->name);
    }
}

