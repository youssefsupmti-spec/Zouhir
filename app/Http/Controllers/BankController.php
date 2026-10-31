<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompteBancaire;
use App\Domain\Auth\Enums\Permission as PermissionEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BankController extends Controller
{
    /**
     * Référentiel bancaire Bank Al-Maghrib et gestion des coordonnées de reversement CRBT.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CompteBancaire::class);
        $user = $request->user();
        $canManage = $user->can(PermissionEnum::MANAGE_USERS->value);

        $search = $request->input('search');
        $bankId = $request->input('bank_id');
        $tab = $request->input('tab', 'comptes'); // 'comptes' ou 'catalogue'
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        // 1. Comptes bancaires des marchands et livreurs
        // Data Isolation : un titulaire ne voit que ses propres comptes.
        $comptesQuery = CompteBancaire::query()->visibleTo($user)
            ->with([
                'bank:id,name,code_etablissement,swift_code,status',
                'owner:id,nom_complet,email,telephone,role',
            ])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('titulaire_nom', 'like', "%{$term}%")
                        ->orWhere('bank_name', 'like', "%{$term}%")
                        ->orWhereHas('owner', function ($u) use ($term) {
                            $u->where('nom_complet', 'like', "%{$term}%");
                        });
                });
            })
            ->when($bankId && $bankId !== 'all', function ($q) use ($bankId) {
                $q->where('bank_id', $bankId);
            })
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc');

        $comptes = $comptesQuery->paginate($perPage)->withQueryString();

        // Masquage sécurisé du RIB en liste (Rapport #16)
        $comptes->through(function ($c) {
            $rawRib = (string) $c->rib;
            $len = strlen($rawRib);
            if ($len >= 8) {
                $c->masked_rib = substr($rawRib, 0, 4) . ' •••• •••• ' . substr($rawRib, -4);
            } else {
                $c->masked_rib = '•••• •••• ••••';
            }
            unset($c->rib);
            return $c;
        });

        // 2. Catalogue complet des Banques BAM
        $banksList = Bank::query()
            ->withCount('comptes')
            ->orderBy('name')
            ->get();

        // Statistiques réelles MariaDB
        $totalBanks = Bank::count();
        $totalComptes = CompteBancaire::query()->visibleTo($user)->count();
        $linkedComptes = CompteBancaire::query()->visibleTo($user)->whereNotNull('bank_id')->count();

        // Liste des acteurs pour le formulaire d'ajout
        $ownersList = $canManage
            ? User::query()->where(fn ($q) => $q->clients())->orWhere(fn ($q) => $q->drivers())
                ->select('id', 'nom_complet', 'role')->orderBy('nom_complet')->get()
            : User::whereKey($user->id)->select('id', 'nom_complet', 'role')->get();

        return Inertia::render('Banks/Index', [
            'comptes' => $comptes,
            'banks' => $banksList,
            'owners' => $ownersList,
            'stats' => [
                'total_banks' => $totalBanks,
                'total_comptes' => $totalComptes,
                'linked_comptes' => $linkedComptes,
                'link_rate' => $totalComptes > 0 ? round(($linkedComptes / $totalComptes) * 100, 1) : 0,
            ],
            'filters' => [
                'search' => $search,
                'bank_id' => $bankId ?? 'all',
                'tab' => $tab,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Enregistrer un nouveau compte bancaire (RIB BAM).
     */
    public function storeCompte(Request $request)
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
            'bank_id' => ['required', 'exists:banks,id'],
            'rib' => ['required', 'string', 'min:24', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        // Nettoyage strict du RIB (chiffres uniquement)
        $cleanRib = preg_replace('/\D/', '', $validated['rib']);
        if (strlen($cleanRib) !== 24) {
            return back()->withErrors(['rib' => 'Le RIB doit comporter exactement 24 chiffres (norme Bank Al-Maghrib).']);
        }

        $bank = Bank::find($validated['bank_id']);
        $owner = \App\Models\User::find($validated['owner_id']);
        $ownerType = $validated['owner_type'] ?? ($owner?->role?->value ?? $owner?->role ?? 'client');

        $isDefault = (bool) ($validated['is_default'] ?? false);
        if ($isDefault) {
            CompteBancaire::where('owner_id', $validated['owner_id'])->update(['is_default' => false]);
        }

        CompteBancaire::create([
            'owner_id' => $validated['owner_id'],
            'owner_type' => $ownerType,
            'titulaire_nom' => $validated['titulaire_nom'],
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'rib' => $cleanRib,
            'is_default' => $isDefault,
        ]);

        return back()->with('success', 'Coordonnées bancaires enregistrées avec succès.');
    }

    /**
     * Mettre à jour un compte bancaire.
     */
    public function updateCompte(Request $request, CompteBancaire $compte)
    {
        Gate::authorize('update', $compte);

        $validated = $request->validate([
            'titulaire_nom' => ['required', 'string', 'max:255'],
            'bank_id' => ['required', 'exists:banks,id'],
            'rib' => ['nullable', 'string', 'min:24', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $bank = Bank::find($validated['bank_id']);
        $compte->bank_id = $bank->id;
        $compte->bank_name = $bank->name;
        $compte->titulaire_nom = $validated['titulaire_nom'];

        if (! empty($validated['rib'])) {
            $cleanRib = preg_replace('/\D/', '', $validated['rib']);
            if (strlen($cleanRib) === 24) {
                $compte->rib = $cleanRib;
            }
        }

        if (isset($validated['is_default']) && $validated['is_default']) {
            CompteBancaire::where('owner_id', $compte->owner_id)->where('id', '!=', $compte->id)->update(['is_default' => false]);
            $compte->is_default = true;
        }

        $compte->save();

        return back()->with('success', 'Coordonnées bancaires mises à jour.');
    }

    /**
     * Supprimer un compte bancaire.
     */
    public function destroyCompte(CompteBancaire $compte)
    {
        Gate::authorize('delete', $compte);
        $compte->delete();

        return back()->with('success', 'Compte bancaire supprimé.');
    }
}

