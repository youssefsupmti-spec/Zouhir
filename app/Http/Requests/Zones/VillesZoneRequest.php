<?php

declare(strict_types=1);

namespace App\Http\Requests\Zones;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;

/** Rattachement / retrait de villes en lot. */
class VillesZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $zone = $this->route('zone');

        return $zone instanceof Zone && (bool) $this->user()?->can('update', $zone);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:2000'],
            'ids.*' => ['integer', 'distinct', 'exists:villes,id'],
        ];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->validated('ids')));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ids.required' => 'Sélectionnez au moins une ville.'];
    }
}
