<?php

namespace App\Http\Requests\RolesPermissions;

use Illuminate\Foundation\Http\FormRequest;

/** Affectation d'un utilisateur existant à un rôle, identifié par son email. */
class RoleMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('role'));
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }

    public function messages(): array
    {
        return ['email.exists' => 'Aucun utilisateur ne correspond à cet email.'];
    }
}
