<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

use App\Models\ClientTarif;
use Illuminate\Foundation\Http\FormRequest;

/** Modification individuelle (lot d'une ligne) ou en lot des tarifs d'UN client. */
class ModifierTarifsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', ClientTarif::class);
    }

    public function rules(): array
    {
        return [
            'client_id' => ClientTarifRules::client(),
            'lignes' => ['required', 'array', 'min:1', 'max:1000'],
            'lignes.*.id' => ['required', 'integer', 'distinct'],
            'lignes.*.livraison' => ClientTarifRules::montant(),
            'lignes.*.refus' => ClientTarifRules::montant(),
            'lignes.*.retour' => ClientTarifRules::montant(),
        ];
    }

    public function messages(): array
    {
        return ClientTarifRules::messages();
    }
}
