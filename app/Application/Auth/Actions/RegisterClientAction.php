<?php

namespace App\Application\Auth\Actions;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Clients\Enums\ClientType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterClientAction
{
    /**
     * Crée un nouveau compte client vérifié et sécurisé.
     * Le rôle est STRICTEMENT forcé à CLIENT.
     */
    public function execute(array $data): User
    {
        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => trim($data['phone']),
            'password' => Hash::make($data['password']),
            'role' => UserRole::CLIENT, // STRICTEMENT forcé côté serveur
            'client_type' => $data['client_type'] instanceof ClientType
                ? $data['client_type']
                : ClientType::from($data['client_type'] ?? 'e_commerce'),
            'store_name' => isset($data['store_name']) ? trim($data['store_name']) : null,
            'city' => trim($data['city']),
            'address' => isset($data['address']) ? trim($data['address']) : null,
            'is_active' => true,
        ]);

        // Assigner le rôle Spatie correspondant
        $user->assignRole(UserRole::CLIENT->value);

        return $user;
    }
}
