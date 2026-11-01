<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

/** Règles partagées des barèmes par ville (standard client, standard livreur) et des commissions. */
final class BaremeRules
{
    /** Ciblage : villes choisies OU zone entière (moins exclusions). */
    public static function ciblage(): array
    {
        return [
            'zone_id' => ['nullable', 'integer', 'exists:zones,id', 'required_without:ville_ids'],
            'ville_ids' => ['nullable', 'array', 'max:2000', 'required_without:zone_id'],
            'ville_ids.*' => ['integer', 'distinct'],
            'exclusions' => ['nullable', 'array', 'max:2000'],
            'exclusions.*' => ['integer'],
        ];
    }

    public static function montant(): array
    {
        return ClientTarifRules::montant();
    }

    public static function existants(): array
    {
        return ['required', 'in:ignorer,mettre_a_jour'];
    }

    public static function delai(): array
    {
        return ['nullable', 'string', 'max:50'];
    }

    public static function messages(): array
    {
        return ClientTarifRules::messages() + [
            'ville_ids.required_without' => 'Sélectionnez des villes ou une zone.',
            'zone_id.required_without' => 'Sélectionnez des villes ou une zone.',
            'existants.in' => 'Choisissez comment traiter les tarifs existants.',
        ];
    }
}
