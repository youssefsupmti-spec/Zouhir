<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Command;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Liste paginée des clients marchands avec recherche, enseignes et RIB masqués.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $ville = $request->input('ville');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 25);

        $sortMap = [
            'ville' => 'ville_name',
            'status' => 'active',
        ];
        $sortColumn = $sortMap[$sort] ?? $sort;

        $allowedSorts = ['id', 'nom_complet', 'email', 'telephone', 'ville_name', 'solde', 'active', 'created_at'];
        if (! in_array($sortColumn, $allowedSorts, true)) {
            $sortColumn = 'created_at';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = User::query()
            ->where('role', 'client')
            ->with([
                'businesses:id,user_id,name,status',
                'comptes_bancaires' => function ($cb) {
                    $cb->select('id', 'owner_id', 'bank_id', 'bank_name', 'rib', 'is_default')
                        ->with('bank:id,name,code_etablissement');
                },
            ])
            ->withCount('commands')
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('nom_complet', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%")
                        ->orWhere('ville_name', 'like', "%{$term}%")
                        ->orWhereHas('businesses', function ($b) use ($term) {
                            $b->where('name', 'like', "%{$term}%");
                        });
                });
            })
            ->when($status && $status !== 'all', function ($q) use ($status) {
                $isActive = in_array($status, ['active', '1', true], true);
                $q->where('active', $isActive);
            })
            ->when($ville, function ($q, $v) {
                $q->where('ville_name', $v);
            })
            ->orderBy($sortColumn, $direction);

        $clients = $query->paginate($perPage)->withQueryString();

        // Masquage strict des RIBs par défaut pour conformité sécurité financière (Rapport #16)
        $clients->through(function ($client) {
            if ($client->comptes_bancaires) {
                $client->comptes_bancaires->transform(function ($compte) {
                    $rawRib = (string) $compte->rib;
                    $len = strlen($rawRib);
                    if ($len >= 8) {
                        $compte->masked_rib = substr($rawRib, 0, 4) . ' •••• •••• ' . substr($rawRib, -4);
                    } else {
                        $compte->masked_rib = '•••• •••• ••••';
                    }
                    // Ne jamais exposer le RIB complet dans la vue de liste index
                    unset($compte->rib);
                    return $compte;
                });
            }
            return $client;
        });

        // Calculs statistiques consolidés MariaDB
        $totalClients = User::where('role', 'client')->count();
        $activeClients = User::where('role', 'client')->where('active', true)->count();
        $totalBusinesses = Business::count();
        $totalCommands = Command::count();

        // Récupérer la liste des villes uniques des marchands pour le filtre
        $villesList = User::where('role', 'client')
            ->whereNotNull('ville_name')
            ->where('ville_name', '!=', '')
            ->distinct()
            ->pluck('ville_name');

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'stats' => [
                'total' => $totalClients,
                'active' => $activeClients,
                'businesses_count' => $totalBusinesses,
                'commands_count' => $totalCommands,
            ],
            'villesList' => $villesList,
            'filters' => [
                'search' => $search,
                'status' => $status ?? 'all',
                'ville' => $ville,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Enregistrer un nouveau client marchand.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_complet' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['required', 'string', 'max:30'],
            'ville_name' => ['nullable', 'string', 'max:100'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,suspended,inactive'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $password = ! empty($validated['password']) ? $validated['password'] : 'ouaiss@2026';
        $isActive = ($validated['status'] ?? 'active') === 'active';

        $client = User::create([
            'nom_complet' => $validated['nom_complet'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'],
            'ville_name' => $validated['ville_name'] ?? null,
            'role' => 'client',
            'active' => $isActive,
            'password' => $password,
        ]);

        // Assigner rôle Spatie si la méthode existe
        if (method_exists($client, 'assignRole')) {
            try {
                $client->assignRole('client');
            } catch (\Throwable $e) {
                // Rôle Spatie déjà géré par la colonne 'role'
            }
        }

        // Créer l'enseigne marchande initiale
        $storeName = $validated['store_name'] ?? ('Enseigne ' . $validated['nom_complet']);
        Business::create([
            'user_id' => $client->id,
            'name' => $storeName,
            'phone' => $validated['telephone'],
            'status' => 'active',
        ]);

        return back()->with('success', 'Client marchand créé avec succès.');
    }

    /**
     * Mettre à jour les informations d'un client.
     */
    public function update(Request $request, User $client)
    {
        $validated = $request->validate([
            'nom_complet' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($client->id)],
            'telephone' => ['required', 'string', 'max:30'],
            'ville_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended,inactive'],
            'password' => ['nullable', 'string', 'min:8'],
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

        $client->update($data);

        return back()->with('success', 'Informations du client mises à jour.');
    }

    /**
     * Supprimer / archiver un client.
     */
    public function destroy(User $client)
    {
        $client->delete();

        return back()->with('success', 'Compte client archivé avec succès.');
    }
}
