<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'marchand@ouaissexpress.com',
            'password' => bcrypt('Secret1234!'),
            'role' => UserRole::CLIENT,
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'marchand@ouaissexpress.com',
            'password' => 'Secret1234!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'marchand@ouaissexpress.com',
            'password' => bcrypt('Secret1234!'),
        ]);

        $this->post('/login', [
            'email' => 'marchand@ouaissexpress.com',
            'password' => 'MauvaisMotDePasse',
        ]);

        $this->assertGuest();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_as_client(): void
    {
        $response = $this->post('/register', [
            'name' => 'Zouhir Marchand',
            'email' => 'zouhir@boutique.ma',
            'phone' => '0661234567',
            'client_type' => 'e_commerce',
            'store_name' => 'Zouhir Store',
            'city' => 'Casablanca',
            'address' => 'Boulevard Zerktouni',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => 'zouhir@boutique.ma',
            'role' => UserRole::CLIENT->value,
            'store_name' => 'Zouhir Store',
        ]);
    }

    public function test_registration_strictly_forces_client_role_even_if_tampered(): void
    {
        $response = $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@test.ma',
            'phone' => '0669999999',
            'client_type' => 'e_commerce',
            'role' => 'admin', // Tente d'escalader les privilèges
            'city' => 'Casablanca',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        
        $user = User::where('email', 'attacker@test.ma')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::CLIENT, $user->role, 'Le rôle doit impérativement rester CLIENT côté serveur');
    }

    public function test_authenticated_users_can_logout(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::CLIENT,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
