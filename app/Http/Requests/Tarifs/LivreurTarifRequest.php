<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

use App\Models\LivreurTarif;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Requêtes d'écriture de l'écran « Tarifs livreurs ». L'action est déduite du nom de route ;
 * l'autorisation passe TOUJOURS par LivreurTarifPolicy.
 */
class LivreurTarifRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return match ($this->route()?->getName()) {
            'tarifs-livreurs.apercu', 'tarifs-livreurs.store' => $u->can('create', LivreurTarif::class),
            'tarifs-livreurs.update' => $u->can('update', LivreurTarif::class),
            'tarifs-livreurs.destroy', 'tarifs-livreurs.restaurer' => $u->can('delete', LivreurTarif::class),
            default => false,
        };
    }

    public function rules(): array
    {
        $livreur = [
            'required', 'integer',
            Rule::exists('users', 'id')->whereNull('deleted_at'),
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! User::drivers()->whereKey((int) $value)->exists()) {
                    $fail('Ce compte n\'est pas un livreur.');
                }
            },
        ];

        return match ($this->route()?->getName()) {
            'tarifs-livreurs.apercu' => ['livreur_id' => $livreur] + BaremeRules::ciblage(),
            'tarifs-livreurs.store' => ['livreur_id' => $livreur] + BaremeRules::ciblage() + [
                'livraison' => BaremeRules::montant(),
                'refus' => BaremeRules::montant(),
                'existants' => BaremeRules::existants(),
                'restaurer' => ['required', 'boolean'],
            ],
            'tarifs-livreurs.update' => [
                'livreur_id' => $livreur,
                'lignes' => ['required', 'array', 'min:1', 'max:1000'],
                'lignes.*.id' => ['required', 'integer', 'distinct'],
                'lignes.*.livraison' => BaremeRules::montant(),
                'lignes.*.refus' => BaremeRules::montant(),
            ],
            'tarifs-livreurs.destroy', 'tarifs-livreurs.restaurer' => [
                'livreur_id' => $livreur,
                'ids' => ['required', 'array', 'min:1', 'max:1000'],
                'ids.*' => ['integer', 'distinct'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return BaremeRules::messages();
    }
}
