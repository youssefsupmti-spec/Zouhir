<?php

namespace App\Http\Controllers;

use App\Models\Command;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class LivreurController extends Controller
{
    /**
     * Liste paginée des livreurs (coursiers DLM) avec métriques de tournées réelles.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        $sortMap = [
            'ville' => 'ville_name',
            'status' => 'active',
        ];
        $sortColumn = $sortMap[$sort] ?? $sort;

        $allowedSorts = ['id', 'nom_complet', 'telephone', 'ville_name', 'active', 'created_at'];
        if (! in_array($sortColumn, $allowedSorts, true)) {
            $sortColumn = 'created_at';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        // Onglets : tous (non archivés) · actifs · archives (suppression logique, restaurable).
        $onglet = in_array($request->input('onglet'), ['tous', 'actifs', 'archives'], true) ? $request->input('onglet') : 'tous';
        // Les comptes techniques des partenaires nationaux ne sont pas des coursiers d'agence.
        $comptesPartenaires = DB::table('delivery_partners')->whereNotNull('user_id')->select('user_id');

        $query = User::drivers()
            ->whereNotIn('users.id', $comptesPartenaires)
            ->when($onglet === 'archives', fn ($q) => $q->onlyTrashed())
            ->when($onglet === 'actifs', fn ($q) => $q->where('active', true))
            ->with(['livreurZones:id,code,name'])
            ->withCount([
                'driverCommands as total_colis_count',
                'driverCommands as colis_livres_count' => function ($q) {
                    $q->where('state_group', 'livre');
                },
                'driverCommands as colis_en_cours_count' => function ($q) {
                    $q->where('state_group', 'en_cours');
                },
            ])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('nom_complet', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%")
                        ->orWhere('ville_name', 'like', "%{$term}%");
                });
            })
            ->when($status && $status !== 'all', function ($q) use ($status) {
                $isActive = in_array($status, ['active', '1', true], true);
                $q->where('active', $isActive);
            })
            ->orderBy($sortColumn, $direction);

        $livreurs = $query->paginate($perPage)->withQueryString();

        // Calcul du taux de succès et de la progression de tournée
        $livreurs->through(function ($livreur) {
            $total = (int) $livreur->total_colis_count;
            $livres = (int) $livreur->colis_livres_count;
            $livreur->taux_succes = $total > 0 ? round(($livres / $total) * 100, 1) : 0;
            $livreur->archive = $livreur->deleted_at !== null;
            $livreur->zones = $livreur->livreurZones->map(fn ($z) => ['id' => $z->id, 'code' => $z->code, 'name' => $z->name])->values();
            $livreur->unsetRelation('livreurZones');

            return $livreur;
        });

        // Métriques de pilotage opérationnel MariaDB
        $totalLivreurs = User::drivers()->whereNotIn('users.id', $comptesPartenaires)->count();
        $activeLivreurs = User::drivers()->whereNotIn('users.id', $comptesPartenaires)->where('active', true)->count();
        $archives = User::drivers()->onlyTrashed()->whereNotIn('users.id', $comptesPartenaires)->count();
        $colisInDistribution = Command::where('state_group', 'en_cours')->count();
        $colisDeliveredTotal = Command::where('state_group', 'livre')->count();

        return Inertia::render('Livreurs/Index', [
            'livreurs' => $livreurs,
            'stats' => [
                'total' => $totalLivreurs,
                'active' => $activeLivreurs,
                'colis_en_cours' => $colisInDistribution,
                'colis_livres_total' => $colisDeliveredTotal,
                'archives' => $archives,
            ],
            'onglet' => $onglet,
            'zonesRegionales' => Zone::query()->where('type', 'locale')->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => [
                'search' => $search,
                'status' => $status ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'onglet' => $onglet,
            ],
        ]);
    }

    /**
     * Enregistrer un nouveau coursier / livreur.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'nom_complet' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['required', 'string', 'max:30'],
            'ville_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended,inactive'],
            'password' => ['required', 'string', Password::defaults()->min(8)],
        ]);

        $password = $validated['password'];
        $isActive = ($validated['status'] ?? 'active') === 'active';

        $livreur = User::create([
            'nom_complet' => $validated['nom_complet'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'],
            'ville_name' => $validated['ville_name'] ?? null,
            'role' => 'livreur',
            'active' => $isActive,
            'password' => $password,
        ]);

        if (method_exists($livreur, 'assignRole')) {
            try {
                $livreur->assignRole('livreur');
            } catch (\Throwable $e) {
                // Rôle Spatie déjà pris en charge
            }
        }

        return back()->with('success', 'Livreur enregistré avec succès.');
    }

    /**
     * Mettre à jour les informations du coursier.
     */
    public function update(Request $request, User $livreur)
    {
        $this->authorizeManaged('update', $livreur);

        $validated = $request->validate([
            'nom_complet' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($livreur->id)],
            'telephone' => ['required', 'string', 'max:30'],
            'ville_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended,inactive'],
            'password' => ['nullable', 'string', Password::defaults()->min(8)],
        ]);

        $data = [
            'nom_complet' => $validated['nom_complet'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'],
            'ville_name' => $validated['ville_name'] ?? null,
        ];

        if (isset($validated['status'])) {
            $data['active'] = $validated['status'] === 'active';
        }

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $livreur->update($data);

        return back()->with('success', 'Fiche livreur mise à jour.');
    }

    /**
     * Désactiver / supprimer un coursier.
     */
    public function destroy(User $livreur)
    {
        $this->authorizeManaged('delete', $livreur);
        $livreur->delete();

        return back()->with('success', 'Livreur archivé avec succès.');
    }

    /** Restaure un coursier archivé (aucune suppression physique). */
    public function restaurer(int $livreur): RedirectResponse
    {
        $cible = User::onlyTrashed()->findOrFail($livreur);
        Gate::authorize('viewAny', User::class);
        Gate::authorize('update', $cible);
        abort_unless(User::drivers()->onlyTrashed()->whereKey($cible->getKey())->exists(), 404);
        $cible->restore();

        return back()->with('success', "Coursier « {$cible->nom_complet} » restauré.");
    }

    /** Édition rapide des zones de livraison d'un coursier (table Zakaria2 livreur_zones). */
    public function zones(Request $request, User $livreur): RedirectResponse
    {
        $this->authorizeManaged('update', $livreur);
        $ids = array_values(array_unique(array_map('intval', $request->validate(['zones' => ['present', 'array', 'max:100'], 'zones.*' => ['integer', 'exists:zones,id']])['zones'])));

        DB::transaction(function () use ($livreur, $ids) {
            DB::table('livreur_zones')->where('user_id', $livreur->id)->where('zone_type', 'livraison')->whereNotIn('zone_id', $ids)->delete();
            foreach ($ids as $z) {
                DB::table('livreur_zones')->insertOrIgnore(['user_id' => $livreur->id, 'zone_id' => $z, 'zone_type' => 'livraison', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return back()->with('success', 'Zones du coursier enregistrées.');
    }

    /**
     * Correctif prise de contrôle de compte : la cible doit appartenir au périmètre
     * de ce module (User::drivers()) et ne jamais être un compte d'administration.
     * Gate::authorize('viewAny') exige manage_users, même si l'utilisateur vise son propre compte.
     */
    private function authorizeManaged(string $ability, User $target): void
    {
        Gate::authorize('viewAny', User::class);
        Gate::authorize($ability, $target);

        abort_unless(User::drivers()->whereKey($target->getKey())->exists(), 404);
        abort_if($target->isAdmin(), 403);
    }
}
