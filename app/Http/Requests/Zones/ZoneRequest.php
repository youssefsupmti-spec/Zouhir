<?php

declare(strict_types=1);

namespace App\Http\Requests\Zones;

use App\Domain\Zones\Enums\TypeZone;
use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création / modification d'une zone : nom et code uniques (corbeille comprise), code en MAJUSCULES. */
class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $zone = $this->route('zone');

        return $zone instanceof Zone
            ? (bool) $this->user()?->can('update', $zone)
            : (bool) $this->user()?->can('create', Zone::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'description' => ($d = trim((string) $this->input('description'))) === '' ? null : $d,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('zone') instanceof Zone ? $this->route('zone')->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('zones', 'name')->ignore($id)],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/', Rule::unique('zones', 'code')->ignore($id)],
            'type' => ['required', Rule::in(TypeZone::valeurs())],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Une zone porte déjà ce nom (corbeille comprise).',
            'code.unique' => 'Ce code est déjà utilisé (corbeille comprise).',
            'code.regex' => 'Le code ne contient que des lettres, chiffres et tirets.',
        ];
    }
}
