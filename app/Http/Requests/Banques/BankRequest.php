<?php

declare(strict_types=1);

namespace App\Http\Requests\Banques;

use App\Domain\Banques\Enums\StatutBanque;
use App\Models\Bank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création / modification d'une banque du référentiel. */
class BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bank = $this->route('bank');

        return $bank instanceof Bank
            ? (bool) $this->user()?->can('update', $bank)
            : (bool) $this->user()?->can('create', Bank::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code_etablissement' => preg_replace('/\D/', '', (string) $this->input('code_etablissement')) ?: null,
            'swift_code' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('swift_code'))) ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('bank') instanceof Bank ? $this->route('bank')->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('banks', 'name')->ignore($id)],
            'code_etablissement' => ['nullable', 'regex:/^\d{3}$/', Rule::unique('banks', 'code_etablissement')->ignore($id)->whereNull('deleted_at')],
            'swift_code' => ['nullable', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/'],
            'status' => ['required', Rule::in(StatutBanque::valeurs())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Cette banque existe déjà (corbeille comprise).',
            'code_etablissement.regex' => 'Le code banque du RIB fait exactement 3 chiffres (ex. 007).',
            'code_etablissement.unique' => 'Ce code banque est déjà utilisé par une autre banque.',
            'swift_code.regex' => 'Code SWIFT invalide : 8 ou 11 caractères (ex. BCMAMAMC).',
        ];
    }
}
