<?php

declare(strict_types=1);

namespace App\Http\Requests\Tarifs;

use App\Models\StandardTarif;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement de l'édition inline du barème standard. La validation fine (montants,
 * villes actives) est faite PAR LIGNE dans le service pour rendre des erreurs ligne à ligne.
 */
class EnregistrerStandardLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', StandardTarif::class);
    }

    public function rules(): array
    {
        return ['lignes' => ['required', 'array', 'min:1', 'max:500']];
    }
}
