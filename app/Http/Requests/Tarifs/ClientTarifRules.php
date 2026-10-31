<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;

/** Règles partagées des requêtes Tarifs Clients. */
final class ClientTarifRules
{
    /** @return list<mixed> */
    public static function client(): array
    {
        return [
            'required',
            'integer',
            Rule::exists('users', 'id')->whereNull('deleted_at'),
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! User::clients()->whereKey((int) $value)->exists()) {
                    $fail('Ce compte n\'est pas un client marchand.');
                }
            },
        ];
    }

    /** Montant obligatoire, ≥ 0, 2 décimales max. Le vide est refusé, 0 est accepté. */
    public static function montant(): array
    {
        return ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            '*.required' => 'Champ obligatoire.',
            'livraison.required' => 'Frais de livraison obligatoires (0 accepté).',
            'refus.required' => 'Frais de refus obligatoires (0 accepté).',
            'retour.required' => 'Frais de retour obligatoires (0 accepté).',
            '*.min' => 'Un tarif ne peut pas être négatif.',
            '*.decimal' => 'Deux décimales au maximum.',
            '*.numeric' => 'Montant numérique attendu.',
            'lignes.*.livraison.required' => 'Frais de livraison obligatoires (0 accepté).',
            'lignes.*.refus.required' => 'Frais de refus obligatoires (0 accepté).',
            'lignes.*.retour.required' => 'Frais de retour obligatoires (0 accepté).',
            'lignes.*.*.min' => 'Un tarif ne peut pas être négatif.',
            'lignes.*.*.decimal' => 'Deux décimales au maximum.',
        ];
    }
}
