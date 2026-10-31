<?php

namespace App\Http\Controllers;

use App\Models\Command;
use App\Models\User;
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

        $query = User::drivers()
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
            return $livreur;
        });

        // Métriques de pilotage opérationnel MariaDB
        $totalLivreurs = User::drivers()->count();
        $activeLivreurs = User::drivers()->where('active', true)->count();
        $colisInDistribution = Command::where('state_group', 'en_cours')->count();
        $colisDeliveredTotal = Command::where('state_group', 'livre')->count();

        return Inertia::render('Livreurs/Index', [
            'livreurs' => $livreurs,
            'stats' => [
                'total' => $totalLivreurs,
                'active' => $activeLivreurs,
                'colis_en_cours' => $colisInDistribution,
                'colis_livres_total' => $colisDeliveredTotal,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
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
