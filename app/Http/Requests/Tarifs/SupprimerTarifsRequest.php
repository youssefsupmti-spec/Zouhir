<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

use App\Models\ClientTarif;
use Illuminate\Foundation\Http\FormRequest;

/** Suppression logique d'un lot de tarifs d'UN client. */
class SupprimerTarifsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', ClientTarif::class);
    }

    public function rules(): array
    {
        return [
            'client_id' => ClientTarifRules::client(),
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return ClientTarifRules::messages() + ['ids.required' => 'Aucun tarif sélectionné.'];
    }
}
