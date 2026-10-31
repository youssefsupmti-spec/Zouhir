<?php

namespace App\Http\Requests\RolesPermissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Création (POST /roles) et modification (PUT /roles/{role}) d'un rôle.
 * Le guard est imposé côté serveur (`web`) : il n'est jamais modifiable.
 */
class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $this->user()->can('update', $role)
            : $this->user()->can('create', Role::class);
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'min:2', 'max:120',
                'regex:/^[\pL\pN _.:\-()]+$/u',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role instanceof Role ? $role->getKey() : null),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'string', 'distinct',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Un rôle portant ce nom existe déjà.',
            'name.regex' => 'Le nom ne peut contenir que des lettres, chiffres, espaces et . _ - : ( ).',
            'permissions.*.exists' => 'La permission « :input » n\'existe pas pour ce guard.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'permissions' => array_values((array) $this->input('permissions', [])),
        ]);
    }
}
