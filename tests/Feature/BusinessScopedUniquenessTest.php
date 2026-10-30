<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessScopedUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_clients_can_have_businesses_with_the_exact_same_name(): void
    {
        $clientA = User::factory()->create([
            'email' => 'clientA@example.com',
            'role' => UserRole::CLIENT,
        ]);

        $clientB = User::factory()->create([
            'email' => 'clientB@example.com',
            'role' => UserRole::CLIENT,
        ]);

        // Client A crée une enseigne "Boutique Express"
        $businessA = Business::create([
            'user_id' => $clientA->id,
            'name' => 'Boutique Express',
            'status' => 'active',
        ]);

        // Client B crée AUSSI une enseigne "Boutique Express"
        $businessB = Business::create([
            'user_id' => $clientB->id,
            'name' => 'Boutique Express',
            'status' => 'active',
        ]);

        $this->assertNotNull($businessA->id);
        $this->assertNotNull($businessB->id);
        $this->assertNotEquals($businessA->id, $businessB->id);
        $this->assertEquals('Boutique Express', $businessA->name);
        $this->assertEquals('Boutique Express', $businessB->name);
        $this->assertEquals(2, Business::where('name', 'Boutique Express')->count(), 'La table autorise le même nom d\'enseigne pour deux clients distincts');
    }

    public function test_same_client_cannot_have_duplicate_business_names(): void
    {
        $client = User::factory()->create([
            'email' => 'client@example.com',
            'role' => UserRole::CLIENT,
        ]);

        Business::create([
            'user_id' => $client->id,
            'name' => 'Mon Enseigne',
            'status' => 'active',
        ]);

        // Tenter de créer un doublon avec le MÊME nom pour le MÊME client
        $this->expectException(QueryException::class);

        Business::create([
            'user_id' => $client->id,
            'name' => 'Mon Enseigne',
            'status' => 'active',
        ]);
    }
}

