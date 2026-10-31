<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

use App\Domain\Tarifs\Clients\Services\ClientTarifEcriture;
use App\Models\ClientTarif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Négociation de nouveaux tarifs (villes sélectionnées ou zone entière).
 * Sert aussi à l'APERÇU (`apercu()` : montants et choix non exigés).
 */
class NegocierTarifsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ClientTarif::class);
    }

    public function rules(): array
    {
        $apercu = $this->routeIs('tarifs-clients.apercu');

        $rules = [
            'client_id' => ClientTarifRules::client(),
            'mode' => ['required', Rule::in(['villes', 'zone'])],
            'zone_id' => ['nullable', 'required_if:mode,zone', 'integer', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'ville_ids' => ['nullable', 'required_if:mode,villes', 'array', 'max:2000'],
            'ville_ids.*' => ['integer', 'distinct', Rule::exists('villes', 'id')->whereNull('deleted_at')],
        ];

        if ($apercu) {
            return $rules;
        }

        return $rules + [
            'livraison' => ClientTarifRules::montant(),
            'refus' => ClientTarifRules::montant(),
            'retour' => ClientTarifRules::montant(),
            'existants' => ['required', Rule::in([ClientTarifEcriture::EXISTANTS_IGNORER, ClientTarifEcriture::EXISTANTS_METTRE_A_JOUR])],
            'restaurer' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ClientTarifRules::messages() + [
            'ville_ids.required_if' => 'Sélectionnez au moins une ville.',
            'zone_id.required_if' => 'Choisissez une zone.',
            'ville_ids.*.exists' => 'Ville inexistante ou archivée.',
        ];
    }
}
