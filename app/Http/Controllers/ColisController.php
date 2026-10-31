<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Command;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ColisController extends Controller
{
    /**
     * Liste paginée des colis et expéditions avec recherche, filtres et métriques CRBT.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Command::class);
        $user = $request->user();

        $search = $request->input('search');
        $stateGroup = $request->input('state_group');
        $villeId = $request->input('ville_id');
        $clientId = $request->input('client_id');
        $livreurId = $request->input('livreur_id');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        $allowedSorts = ['id', 'code', 'prix_brut', 'destinataire_nom', 'created_at', 'livre_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        // Data Isolation : toute la page (liste ET statistiques) est limitée au périmètre.
        $scoped = fn () => Command::query()->visibleTo($user);

        $query = $scoped()
            ->with([
                'client:id,nom_complet,email,telephone',
                'business:id,name',
                'livreur:id,nom_complet,telephone',
                'ville:id,name_fr,code_zone',
            ])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('code', 'like', "%{$term}%")
                        ->orWhere('destinataire_nom', 'like', "%{$term}%")
                        ->orWhere('destinataire_telephone', 'like', "%{$term}%")
                        ->orWhere('produit', 'like', "%{$term}%")
                        ->orWhere('business_name', 'like', "%{$term}%")
                        ->orWhere('nom_cliente', 'like', "%{$term}%");
                });
            })
            ->when($stateGroup && $stateGroup !== 'all', function ($q) use ($stateGroup) {
                $q->where('state_group', $stateGroup);
            })
            ->when($villeId && $villeId !== 'all', function ($q) use ($villeId) {
                $q->where('destinataire_ville_id', $villeId);
            })
            ->when($clientId && $clientId !== 'all', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            })
            ->when($livreurId && $livreurId !== 'all', function ($q) use ($livreurId) {
                $q->where('livreur_id', $livreurId);
            })
            ->orderBy($sort, $direction);

        $colis = $query->paginate($perPage)->withQueryString();

        // Capacités calculées par les Policies : l'interface n'utilise aucun nom de rôle.
        $colis->through(function (Command $c) use ($user) {
            $c->setAttribute('can', [
                'update' => $user->can('update', $c),
                'updateState' => $user->can('updateState', $c),
                'delete' => $user->can('delete', $c),
            ]);

            return $c;
        });

        // Statistiques logistiques et financières réelles MariaDB
        $totalColis = $scoped()->count();
        $livresColis = $scoped()->where('state_group', 'livre')->count();
        $enCoursColis = $scoped()->where('state_group', 'en_cours')->count();
        $retoursColis = $scoped()->whereIn('state_group', ['retour', 'annule'])->count();
        $tauxLivraison = $totalColis > 0 ? round(($livresColis / $totalColis) * 100, 1) : 0;
        $totalCrbtLivre = (float) $scoped()->where('state_group', 'livre')->sum('prix_brut');

        // Référentiels pour les filtres et les formulaires d'expédition
        // Listes de référence limitées au périmètre : un marchand ne voit que lui-même,
        // seule l'administration voit les autres marchands et les livreurs.
        $clientsList = User::clients()
            ->when(! $user->isAdmin(), fn ($q) => $q->whereKey($user->id))
            ->with(['businesses:id,user_id,name'])
            ->select('id', 'nom_complet', 'telephone')
            ->orderBy('nom_complet')
            ->get();

        $villesList = Ville::select('id', 'name_fr', 'code_zone')
            ->orderBy('name_fr')
            ->get();

        $livreursList = User::drivers()
            ->when(! $user->isAdmin(), fn ($q) => $q->whereRaw('1 = 0'))
            ->select('id', 'nom_complet', 'telephone')
            ->orderBy('nom_complet')
            ->get();

        return Inertia::render('Colis/Index', [
            'colis' => $colis,
            'canCreate' => $user->can('create', Command::class),
            'clients' => $clientsList,
            'villes' => $villesList,
            'livreurs' => $livreursList,
            'stats' => [
                'total' => $totalColis,
                'livres' => $livresColis,
                'en_cours' => $enCoursColis,
                'retours' => $retoursColis,
                'taux_livraison' => $tauxLivraison,
                'total_crbt' => $totalCrbtLivre,
            ],
            'filters' => [
                'search' => $search,
                'state_group' => $stateGroup ?? 'all',
                'ville_id' => $villeId ?? 'all',
                'client_id' => $clientId ?? 'all',
                'livreur_id' => $livreurId ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Enregistrer un nouveau colis express.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Command::class);
        $user = $request->user();

        // Le client_id est forcé côté serveur hors administration.
        if (! $user->isAdmin()) {
            $request->merge(['client_id' => $user->id]);
        }

        $validated = $request->validate([
            'client_id' => ['required', 'exists:users,id'],
            'business_id' => ['nullable', 'exists:businesses,id'],
            'produit' => ['nullable', 'string', 'max:255'],
            'quantite' => ['nullable', 'integer', 'min:1'],
            'destinataire_nom' => ['required', 'string', 'max:255'],
            'destinataire_telephone' => ['required', 'string', 'max:30'],
            'destinataire_adresse' => ['nullable', 'string'],
            'destinataire_ville_id' => ['required', 'exists:villes,id'],
            'prix_brut' => ['required', 'numeric', 'min:0'],
            'frais_livraison_client' => ['nullable', 'numeric', 'min:0'],
            'frais_livraison_livreur' => ['nullable', 'numeric', 'min:0'],
            'livreur_id' => ['nullable', 'exists:users,id'],
            'ouverture_autorisee' => ['nullable', 'boolean'],
            'echange' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $client = User::find($validated['client_id']);
        $ville = Ville::find($validated['destinataire_ville_id']);
        // L'enseigne doit appartenir au client expéditeur.
        $business = ! empty($validated['business_id'])
            ? Business::where('user_id', $client->id)->findOrFail($validated['business_id'])
            : null;
        // L'affectation d'un livreur est une opération d'administration.
        $livreur = ($user->isAdmin() && ! empty($validated['livreur_id']))
            ? User::drivers()->findOrFail($validated['livreur_id'])
            : null;

        $code = $this->generateUniqueCode();

        $data = [
            'code' => $code,
            'produit' => $validated['produit'] ?? 'Marchandise',
            'quantite' => $validated['quantite'] ?? 1,
            'client_id' => $client->id,
            'business_id' => $business?->id,
            'business_name' => $business?->name,
            'nom_cliente' => $client->nom_complet,
            'livreur_id' => $livreur?->id,
            'livreur_nom' => $livreur?->nom_complet,
            'livreur_telephone' => $livreur?->telephone,
            'destinataire_nom' => $validated['destinataire_nom'],
            'destinataire_telephone' => $validated['destinataire_telephone'],
            'destinataire_adresse' => $validated['destinataire_adresse'] ?? null,
            'destinataire_ville' => $ville->name_fr,
            'destinataire_ville_id' => $ville->id,
            'destinataire_zone_code' => $ville->code_zone,
            'prix_brut' => $validated['prix_brut'],
            'frais_livraison_client' => $validated['frais_livraison_client'] ?? 35.00,
            'frais_livraison_livreur' => $validated['frais_livraison_livreur'] ?? 15.00,
            'state' => 'Nouveau Colis',
            'state_group' => 'nouveau',
            'ouverture_autorisee' => (bool) ($validated['ouverture_autorisee'] ?? false),
            'echange' => (bool) ($validated['echange'] ?? false),
            'note' => $validated['note'] ?? null,
        ];

        Command::create($data);

        return back()->with('success', "Colis #{$code} créé avec succès.");
    }

    /**
     * Mettre à jour un colis.
     */
    public function update(Request $request, Command $coli)
    {
        Gate::authorize('update', $coli);
        $isAdmin = $request->user()->isAdmin();

        $validated = $request->validate([
            'produit' => ['nullable', 'string', 'max:255'],
            'quantite' => ['nullable', 'integer', 'min:1'],
            'destinataire_nom' => ['required', 'string', 'max:255'],
            'destinataire_telephone' => ['required', 'string', 'max:30'],
            'destinataire_adresse' => ['nullable', 'string'],
            'destinataire_ville_id' => ['required', 'exists:villes,id'],
            'prix_brut' => ['required', 'numeric', 'min:0'],
            'frais_livraison_client' => ['nullable', 'numeric', 'min:0'],
            'livreur_id' => ['nullable', 'exists:users,id'],
            'ouverture_autorisee' => ['nullable', 'boolean'],
            'echange' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $ville = Ville::find($validated['destinataire_ville_id']);
        // Hors administration, l'affectation du livreur reste inchangée.
        $livreur = $isAdmin
            ? (! empty($validated['livreur_id']) ? User::drivers()->findOrFail($validated['livreur_id']) : null)
            : $coli->livreur;

        $coli->update([
            'produit' => $validated['produit'] ?? $coli->produit,
            'quantite' => $validated['quantite'] ?? $coli->quantite,
            'destinataire_nom' => $validated['destinataire_nom'],
            'destinataire_telephone' => $validated['destinataire_telephone'],
            'destinataire_adresse' => $validated['destinataire_adresse'] ?? null,
            'destinataire_ville' => $ville->name_fr,
            'destinataire_ville_id' => $ville->id,
            'destinataire_zone_code' => $ville->code_zone,
            'prix_brut' => $validated['prix_brut'],
            'frais_livraison_client' => $validated['frais_livraison_client'] ?? $coli->frais_livraison_client,
            'livreur_id' => $livreur?->id,
            'livreur_nom' => $livreur?->nom_complet,
            'livreur_telephone' => $livreur?->telephone,
            'ouverture_autorisee' => (bool) ($validated['ouverture_autorisee'] ?? false),
            'echange' => (bool) ($validated['echange'] ?? false),
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', "Colis #{$coli->code} mis à jour.");
    }

    /**
     * Changer le statut logistique d'un colis.
     */
    public function updateState(Request $request, Command $coli)
    {
        Gate::authorize('updateState', $coli);
        $isAdmin = $request->user()->isAdmin();

        $validated = $request->validate([
            'state_group' => ['required', 'in:nouveau,en_cours,livre,retour,annule'],
            'livreur_id' => ['nullable', 'exists:users,id'],
            'note' => ['nullable', 'string'],
        ]);

        $stateMap = [
            'nouveau' => 'Nouveau Colis',
            'en_cours' => 'En cours de distribution',
            'livre' => 'Livré avec succès',
            'retour' => 'Retour à l\'expéditeur',
            'annule' => 'Annulé par le client',
        ];

        $group = $validated['state_group'];
        $coli->state_group = $group;
        $coli->state = $stateMap[$group] ?? $group;

        if ($group === 'livre' && ! $coli->livre_at) {
            $coli->livre_at = now();
        }

        if ($group === 'en_cours' && ! $coli->expedie_at) {
            $coli->expedie_at = now();
        }

        if ($isAdmin && ! empty($validated['livreur_id'])) {
            $livreur = User::drivers()->findOrFail($validated['livreur_id']);
            $coli->livreur_id = $livreur->id;
            $coli->livreur_nom = $livreur->nom_complet;
            $coli->livreur_telephone = $livreur->telephone;
        }

        if (! empty($validated['note'])) {
            $coli->note = $validated['note'];
        }

        $coli->save();

        return back()->with('success', "Statut du colis #{$coli->code} mis à jour : {$coli->state}.");
    }

    /**
     * Supprimer / archiver un colis.
     */
    public function destroy(Command $coli)
    {
        Gate::authorize('delete', $coli);
        $coli->delete();

        return back()->with('success', "Colis #{$coli->code} archivé avec succès.");
    }

    /**
     * Générer un code unique AWB (Air Waybill) pour OUAISS EXPRESS.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = 'OUA-' . strtoupper(Str::random(8));
        } while (Command::where('code', $code)->exists());

        return $code;
    }
}

