<?php

namespace Tests\Feature\Banques;

use App\Models\Bank;
use App\Models\Business;
use App\Models\CompteBancaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tarifs\TarifsFixtures;
use Tests\TestCase;

class BanquesEtComptesTest extends TestCase
{
    use RefreshDatabase;
    use TarifsFixtures;

    private Bank $awb;

    private Bank $cih;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTarifs();
        $this->awb = Bank::create(['name' => 'Attijariwafa Bank', 'code_etablissement' => '007', 'swift_code' => 'BCMAMAMC', 'status' => 'active']);
        $this->cih = Bank::create(['name' => 'CIH Bank', 'code_etablissement' => '230', 'status' => 'active']);
        Bank::create(['name' => 'Umnia Bank', 'code_etablissement' => '240', 'status' => 'inactive']);
    }

    private function compteDe($owner, Bank $bank, string $rib, string $type = 'client'): CompteBancaire
    {
        return CompteBancaire::create(['owner_id' => $owner->id, 'owner_type' => $type, 'titulaire_nom' => $owner->nom_complet,
            'bank_id' => $bank->id, 'bank_name' => $bank->name, 'rib' => $rib, 'is_default' => true]);
    }

    public function test_page_banques_liste_uniquement_les_banques_avec_onglets(): void
    {
        $this->compteDe($this->clientA, $this->cih, '230810123456789012345678');

        $this->actingAs($this->admin)->get('/banks')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Banks/Index')
            ->missing('comptes')
            ->has('banks.data', 3)
            ->where('banks.data.0.name', 'Attijariwafa Bank')
            ->where('banks.data.1.comptes_count', 1)
            ->where('stats.toutes', 3)
            ->where('stats.active', 2)
            ->where('stats.inactive', 1)
            ->where('stats.comptes', 1)
            ->where('can.manage', true));

        $this->actingAs($this->admin)->get('/banks?onglet=inactive')->assertInertia(fn (Assert $p) => $p->has('banks.data', 1)->where('banks.data.0.name', 'Umnia Bank'));
        $this->actingAs($this->admin)->get('/banks?search=BCMA')->assertInertia(fn (Assert $p) => $p->has('banks.data', 1));
        $this->actingAs($this->clientA)->get('/banks')->assertForbidden();
    }

    public function test_crud_banque_statut_corbeille_et_restauration(): void
    {
        $this->actingAs($this->admin)->post('/banks', ['name' => 'CFG Bank', 'code_etablissement' => '182', 'swift_code' => 'cfgb mamc', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $cfg = Bank::where('name', 'CFG Bank')->firstOrFail();
        $this->assertSame('CFGBMAMC', $cfg->swift_code);

        // Doublons et formats refusés.
        $this->actingAs($this->admin)->post('/banks', ['name' => 'CFG Bank', 'status' => 'active'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post('/banks', ['name' => 'X', 'code_etablissement' => '007', 'status' => 'active'])->assertSessionHasErrors('code_etablissement');
        $this->actingAs($this->admin)->post('/banks', ['name' => 'Y', 'code_etablissement' => '12', 'status' => 'active'])->assertSessionHasErrors('code_etablissement');
        $this->actingAs($this->clientA)->post('/banks', ['name' => 'Z', 'status' => 'active'])->assertForbidden();

        // Renommer reporte le nom sur les comptes.
        $compte = $this->compteDe($this->clientA, $this->cih, '230810123456789012345678');
        $this->actingAs($this->admin)->put("/banks/{$this->cih->id}", ['name' => 'CIH Bank SA', 'code_etablissement' => '230', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('CIH Bank SA', $compte->fresh()->bank_name);

        $this->actingAs($this->admin)->patch("/banks/{$this->cih->id}/statut", ['status' => 'suspendue'])->assertSessionHasNoErrors();
        $this->assertSame('suspendue', $this->cih->fresh()->status);

        // Banque utilisée : corbeille refusée.
        $this->actingAs($this->admin)->delete("/banks/{$this->cih->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('banks', ['id' => $this->cih->id]);

        $this->actingAs($this->admin)->delete("/banks/{$cfg->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('banks', ['id' => $cfg->id]);
        $this->actingAs($this->admin)->get('/banks?onglet=corbeille')->assertInertia(fn (Assert $p) => $p->has('banks.data', 1));
        $this->actingAs($this->admin)->post("/banks/{$cfg->id}/restaurer")->assertSessionHas('success');
        $this->assertNotSoftDeleted('banks', ['id' => $cfg->id]);
    }

    public function test_page_comptes_bancaires_de_tous_les_business(): void
    {
        Business::create(['user_id' => $this->clientA->id, 'name' => 'Boutique Fatiha', 'status' => 'active']);
        Business::create(['user_id' => $this->clientB->id, 'name' => 'Atlas Shop', 'status' => 'active']);
        $this->compteDe($this->clientA, $this->cih, '230810123456789012345678');
        $this->compteDe($this->clientB, $this->awb, '0078 2500 0111'); // incomplet

        $this->actingAs($this->admin)->get('/comptes-bancaires')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('ComptesBancaires/Index')
            ->has('comptes.data', 2)
            ->missing('comptes.data.0.rib')
            ->where('stats.total_comptes', 2)
            ->where('stats.a_verifier', 1)
            ->where('stats.business_total', 2)
            ->where('stats.business_avec_rib', 2)
            ->has('banks', 3));

        $this->actingAs($this->admin)->get('/comptes-bancaires?search=atlas')->assertInertia(fn (Assert $p) => $p
            ->has('comptes.data', 1)
            ->where('comptes.data.0.business.0', 'Atlas Shop')
            ->where('comptes.data.0.rib_conforme', false));
        $this->actingAs($this->admin)->get("/comptes-bancaires?bank_id={$this->cih->id}")->assertInertia(fn (Assert $p) => $p
            ->has('comptes.data', 1)
            ->where('comptes.data.0.masked_rib', '2308 •••• •••• 5678')
            ->where('comptes.data.0.code_coherent', true));
        $this->actingAs($this->admin)->get('/comptes-bancaires?onglet=a_verifier')->assertInertia(fn (Assert $p) => $p->has('comptes.data', 1));

        // Un marchand ne voit que ses comptes et ses business.
        $this->actingAs($this->clientA)->get('/comptes-bancaires')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('comptes.data', 1)
            ->where('stats.business_total', 1));
    }

    public function test_un_nouveau_rib_exige_une_banque_active(): void
    {
        $umnia = Bank::where('name', 'Umnia Bank')->firstOrFail();
        $this->actingAs($this->admin)->post('/comptes-bancaires', [
            'owner_id' => $this->clientA->id, 'titulaire_nom' => 'Fatiha', 'bank_id' => $umnia->id, 'rib' => '240810123456789012345678',
        ])->assertSessionHasErrors('bank_id');

        // Un compte garde sa banque même si elle est désactivée ensuite.
        $compte = $this->compteDe($this->clientA, $this->cih, '230810123456789012345678');
        $this->cih->update(['status' => 'inactive']);
        $this->actingAs($this->admin)->put("/comptes-bancaires/{$compte->id}", ['titulaire_nom' => 'Fatiha A.', 'bank_id' => $this->cih->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('Fatiha A.', $compte->fresh()->titulaire_nom);
    }
}
