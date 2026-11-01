<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

use App\Models\StandardTarif;
use Illuminate\Foundation\Http\FormRequest;

/** Ajout en masse / application à une zone du barème standard de livraison (aperçu ou écriture). */
class AppliquerStandardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StandardTarif::class);
    }

    public function rules(): array
    {
        $ecriture = $this->routeIs('tarifs-standard.store');

        return BaremeRules::ciblage() + ($ecriture ? [
            'livraison' => BaremeRules::montant(),
            'refus' => BaremeRules::montant(),
            'retour' => BaremeRules::montant(),
            'delai' => BaremeRules::delai(),
            'existants' => BaremeRules::existants(),
        ] : []);
    }

    public function messages(): array
    {
        return BaremeRules::messages();
    }
}
