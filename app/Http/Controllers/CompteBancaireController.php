<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\Bank;
use App\Models\Business;
use App\Models\CompteBancaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Comptes bancaires » : les RIB de reversement CRBT de tous les business (gestion des
 * comptes, `manage_users`) ou de son propre compte (`view_finances`). Le RIB en clair ne
 * quitte jamais le serveur en liste : il est masqué.
 */
class CompteBancaireController extends Controller
{
    public const ONGLETS = ['tous', 'client', 'livreur', 'a_verifier'];

    /** RIB marocain : 24 chiffres une fois les espaces retirés. */
    private const SQL_RIB_NON_CONFORME = "LENGTH(REPLACE(comptes_bancaires.rib, ' ', '')) <> 24";

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CompteBancaire::class);
        $user = $request->user();
        $canManage = $user->can(PermissionEnum::MANAGE_USERS->value);

        $onglet = in_array($request->input('onglet'), self::ONGLETS, true) ? (string) $request->input('onglet') : 'tous';
        $search = trim((string) $request->input('search', ''));
        $bankId = (int) $request->input('bank_id') ?: null;
        $parPage = min(max((int) $request->input('per_page', 25), 10), 100);

        $base = fn (): Builder => CompteBancaire::query()->visibleTo($user);

        $comptes = $base()
            ->with(['bank:id,name,code_etablissement,status', 'owner:id,nom_complet,email,telephone,role', 'owner.businesses:id,user_id,name'])
            ->when(in_array($onglet, ['client', 'livreur'], true), fn ($q) => $q->where('owner_type', $onglet))
            ->when($onglet === 'a_verifier', fn ($q) => $q->whereRaw(self::SQL_RIB_NON_CONFORME))
            ->when($bankId, fn ($q) => $q->where('bank_id', $bankId))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('titulaire_nom', 'like', "%{$search}%")
                ->orWhere('bank_name', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($u) => $u->where('nom_complet', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('businesses', fn ($b) => $b->where('name', 'like', "%{$search}%")))))
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->paginate($parPage)
            ->withQueryString()
            ->through(fn (CompteBancaire $c) => $this->ligne($c));

        $s = $base()
            ->selectRaw('COUNT(*) as tous')
            ->selectRaw("SUM(CASE WHEN owner_type = 'client' THEN 1 ELSE 0 END) as client")
            ->selectRaw("SUM(CASE WHEN owner_type = 'livreur' THEN 1 ELSE 0 END) as livreur")
            ->selectRaw('SUM(CASE WHEN '.self::SQL_RIB_NON_CONFORME.' THEN 1 ELSE 0 END) as a_verifier')
            ->selectRaw('COUNT(DISTINCT owner_id) as titulaires')
            ->first();

        $titulaires = $base()->select('owner_id');
        $business = Business::query()->visibleTo($user);
        $businessTotal = (clone $business)->count();
        $businessAvecRib = (clone $business)->whereIn('user_id', $titulaires)->count();

        $owners = $canManage
            ? User::query()->where(fn ($q) => $q->clients())->orWhere(fn ($q) => $q->drivers())
                ->with('businesses:id,user_id,name')
                ->select('id', 'nom_complet', 'role')->orderBy('nom_complet')->get()
            : User::query()->whereKey($user->id)->with('businesses:id,user_id,name')->select('id', 'nom_complet', 'role')->get();

        return Inertia::render('ComptesBancaires/Index', [
            'comptes' => $comptes,
            'banks' => Bank::query()->orderBy('name')->get(['id', 'name', 'code_etablissement', 'status']),
            'owners' => $owners->map(fn (User $o) => [
                'id' => (int) $o->id,
                'nom_complet' => (string) $o->nom_complet,
                'role' => $o->role?->value ?? $o->role,
                'business' => $o->businesses->pluck('name')->implode(', ') ?: null,
            ])->values(),
            'stats' => [
                'total_comptes' => (int) ($s->tous ?? 0),
                'client' => (int) ($s->client ?? 0),
                'livreur' => (int) ($s->livreur ?? 0),
                'a_verifier' => (int) ($s->a_verifier ?? 0),
                'titulaires' => (int) ($s->titulaires ?? 0),
                'business_total' => $businessTotal,
                'business_avec_rib' => $businessAvecRib,
            ],
            'filters' => ['onglet' => $onglet, 'search' => $search, 'bank_id' => $bankId, 'per_page' => $parPage],
            'can' => ['manage' => $canManage],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CompteBancaire::class);

        // Hors gestion des comptes, le titulaire est forcé à l'utilisateur connecté.
        if (! $request->user()->can(PermissionEnum::MANAGE_USERS->value)) {
            $request->merge(['owner_id' => $request->user()->id]);
        }

        $validated = $request->validate([
            'owner_id' => ['required', 'exists:users,id'],
            'owner_type' => ['nullable', 'in:client,livreur,agence'],
            'titulaire_nom' => ['required', 'string', 'max:255'],
            'bank_id' => ['required', Rule::exists('banks', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'rib' => ['required', 'string', 'min:24', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ], ['bank_id.exists' => 'Choisissez une banque active du référentiel.']);

        $rib = preg_replace('/\D/', '', $validated['rib']);
        if (strlen($rib) !== 24) {
            return back()->withErrors(['rib' => 'Le RIB doit comporter exactement 24 chiffres (norme Bank Al-Maghrib).']);
        }

        $bank = Bank::query()->findOrFail($validated['bank_id']);
        $owner = User::query()->find($validated['owner_id']);
        $ownerType = $validated['owner_type'] ?? ($owner?->role?->value ?? $owner?->role ?? 'client');
        $isDefault = (bool) ($validated['is_default'] ?? false);

        DB::transaction(function () use ($validated, $ownerType, $bank, $rib, $isDefault) {
            if ($isDefault) {
                CompteBancaire::query()->where('owner_id', $validated['owner_id'])->update(['is_default' => false]);
            }
            CompteBancaire::query()->create([
                'owner_id' => $validated['owner_id'],
                'owner_type' => $ownerType,
                'titulaire_nom' => $validated['titulaire_nom'],
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'rib' => $rib,
                'is_default' => $isDefault,
            ]);
        });

        return back()->with('success', 'Coordonnées bancaires enregistrées avec succès.');
    }

    public function update(Request $request, CompteBancaire $compte): RedirectResponse
    {
        Gate::authorize('update', $compte);

        $validated = $request->validate([
            'titulaire_nom' => ['required', 'string', 'max:255'],
            // La banque actuelle reste acceptée même désactivée ; une nouvelle banque doit être active.
            'bank_id' => ['required', Rule::exists('banks', 'id')->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('status', 'active')->orWhere('id', (int) $compte->bank_id))],
            'rib' => ['nullable', 'string', 'min:24', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ], ['bank_id.exists' => 'Choisissez une banque active du référentiel.']);

        if (! empty($validated['rib']) && strlen(preg_replace('/\D/', '', $validated['rib'])) !== 24) {
            return back()->withErrors(['rib' => 'Le RIB doit comporter exactement 24 chiffres (norme Bank Al-Maghrib).']);
        }

        DB::transaction(function () use ($validated, $compte) {
            $bank = Bank::query()->findOrFail($validated['bank_id']);
            $compte->bank_id = $bank->id;
            $compte->bank_name = $bank->name;
            $compte->titulaire_nom = $validated['titulaire_nom'];
            if (! empty($validated['rib'])) {
                $compte->rib = preg_replace('/\D/', '', $validated['rib']);
            }
            if (! empty($validated['is_default'])) {
                CompteBancaire::query()->where('owner_id', $compte->owner_id)->whereKeyNot($compte->id)->update(['is_default' => false]);
                $compte->is_default = true;
            }
            $compte->save();
        });

        return back()->with('success', 'Coordonnées bancaires mises à jour.');
    }

    public function destroy(CompteBancaire $compte): RedirectResponse
    {
        Gate::authorize('delete', $compte);
        $compte->delete();

        return back()->with('success', 'Compte bancaire supprimé.');
    }

    /** @return array<string, mixed> */
    private function ligne(CompteBancaire $c): array
    {
        $rib = preg_replace('/\D/', '', (string) $c->rib);
        $codeBanque = $c->bank?->code_etablissement;

        return [
            'id' => (int) $c->id,
            'owner_id' => (int) $c->owner_id,
            'owner_type' => $c->owner_type,
            'titulaire_nom' => (string) $c->titulaire_nom,
            'bank_id' => $c->bank_id,
            'bank_name' => $c->bank?->name ?? $c->bank_name,
            'bank_code' => $codeBanque,
            'bank_active' => $c->bank === null || $c->bank->status === 'active',
            'masked_rib' => strlen($rib) >= 8 ? substr($rib, 0, 4).' •••• •••• '.substr($rib, -4) : '•••• •••• ••••',
            'rib_conforme' => strlen($rib) === 24,
            // Les 3 premiers chiffres du RIB sont le code de la banque.
            'code_coherent' => $codeBanque === null || strlen($rib) < 3 || str_starts_with($rib, $codeBanque),
            'is_default' => (bool) $c->is_default,
            'owner' => $c->owner ? [
                'id' => (int) $c->owner->id,
                'nom_complet' => (string) $c->owner->nom_complet,
                'email' => $c->owner->email,
                'telephone' => $c->owner->telephone,
            ] : null,
            'business' => $c->owner?->businesses->pluck('name')->values()->all() ?? [],
        ];
    }
}
