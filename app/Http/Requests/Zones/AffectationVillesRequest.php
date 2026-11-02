<?php

declare(strict_types=1);

namespace App\Http\Requests\Zones;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Onglet « Villes sans zone » : affectation en lot `[{ville_id, zone_id}]`. */
class AffectationVillesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Zone::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'affectations' => ['required', 'array', 'min:1', 'max:500'],
            'affectations.*.ville_id' => ['required', 'integer', 'distinct', Rule::exists('villes', 'id')->whereNull('deleted_at')],
            'affectations.*.zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<int, int> ville_id => zone_id */
    public function affectations(): array
    {
        $out = [];
        foreach ((array) $this->validated('affectations') as $a) {
            $out[(int) $a['ville_id']] = (int) $a['zone_id'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['affectations.required' => 'Choisissez une zone pour au moins une ville.'];
    }
}
