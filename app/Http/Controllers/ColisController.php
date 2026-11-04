<?php

namespace App\Http\Controllers;

use App\Application\Tarifs\Actions\FigerCommissionLivreurColis;
use App\Application\Tarifs\Actions\FigerTarifClientColis;
use App\Models\Business;
use App\Models\Command;
use App\Models\CommandStatusHistory;
use App\Models\NoteMessage;
use App\Models\Statut;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'canSetManualFee' => $user->isAdmin(),
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
            'state' => 'Nouveau Colis',
            'state_group' => 'nouveau',
            'ouverture_autorisee' => (bool) ($validated['ouverture_autorisee'] ?? false),
            'echange' => (bool) ($validated['echange'] ?? false),
            'note' => $validated['note'] ?? null,
        ];

        // Snapshot tarifaire (Sprint 28) : tarif négocié → standard → anomalie « missing ».
        // Seule l'administration peut imposer un montant manuel ; aucune valeur par défaut inventée.
        $colis = new Command($data);
        $tarif = app(FigerTarifClientColis::class)->aLaCreation($colis, $this->montantManuel($request, $validated));
        // Commission livreur (Sprint 29) : snapshot séparé, aucune valeur par défaut inventée.
        app(FigerCommissionLivreurColis::class)->aLaCreation($colis, $this->commissionManuelle($request, $validated));
        $colis->save();

        $message = "Colis #{$code} créé avec succès.";
        if ($tarif->estManquant()) {
            return back()->with('success', $message)
                ->with('error', "Aucun tarif négocié ni standard pour {$ville->name_fr} : frais client à compléter (anomalie « missing »).");
        }

        return back()->with('success', $message);
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
            'frais_livraison_livreur' => ['nullable', 'numeric', 'min:0'],
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

        $coli->fill([
            'produit' => $validated['produit'] ?? $coli->produit,
            'quantite' => $validated['quantite'] ?? $coli->quantite,
            'destinataire_nom' => $validated['destinataire_nom'],
            'destinataire_telephone' => $validated['destinataire_telephone'],
            'destinataire_adresse' => $validated['destinataire_adresse'] ?? null,
            'destinataire_ville' => $ville->name_fr,
            'destinataire_ville_id' => $ville->id,
            'destinataire_zone_code' => $ville->code_zone,
            'prix_brut' => $validated['prix_brut'],
            'livreur_id' => $livreur?->id,
            'livreur_nom' => $livreur?->nom_complet,
            'livreur_telephone' => $livreur?->telephone,
            'ouverture_autorisee' => (bool) ($validated['ouverture_autorisee'] ?? false),
            'echange' => (bool) ($validated['echange'] ?? false),
            'note' => $validated['note'] ?? null,
        ]);

        // Snapshot : recalcul uniquement si la ville change sur un colis non finalisé et non manuel.
        app(FigerTarifClientColis::class)->aLaModification($coli, $this->montantManuel($request, $validated));
        app(FigerCommissionLivreurColis::class)->aLaModification($coli, $this->commissionManuelle($request, $validated));
        $coli->save();

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
        $etatAvant = $coli->state;
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

        // La note saisie devient un message : la note d'origine (reprise de l'ancien site) reste intacte.
        $noteSaisie = trim((string) ($validated['note'] ?? ''));

        // Changement de livreur avant finalisation : commission réalignée sur le nouveau livreur.
        app(FigerCommissionLivreurColis::class)->aLaModification($coli);
        $coli->statut_id = Statut::query()->where('libelle', $coli->state)->orderByDesc('actif')->orderBy('id')->value('id');

        DB::transaction(function () use ($coli, $etatAvant, $noteSaisie, $request) {
            $coli->save();
            $user = $request->user();
            $type = $user->isAdmin() ? 'moderateur' : ($user->isDriver() ? 'livreur' : ($user->isClient() ? 'client' : null));

            if ($etatAvant !== $coli->state) {
                CommandStatusHistory::create([
                    'command_id' => $coli->id,
                    'command_code' => $coli->code,
                    'from_state' => $etatAvant,
                    'to_state' => $coli->state,
                    'to_statut_id' => $coli->statut_id,
                    'changed_by_type' => $type,
                    'changed_by_id' => $user->id,
                    'changed_by_nom' => $user->nom_complet,
                    'change_source' => 'application',
                    'created_at' => now(),
                ]);
            }

            if ($noteSaisie !== '') {
                NoteMessage::create([
                    'command_id' => $coli->id,
                    'user_id' => $user->id,
                    'role' => $type ?? 'inconnu',
                    'auteur_nom' => $user->nom_complet,
                    'message' => $noteSaisie,
                    'position' => (int) NoteMessage::where('command_id', $coli->id)->where('source', 'application')->max('position') + 1,
                    'source' => 'application',
                    'date_exacte' => true,
                ]);
            }
        });

        return back()->with('success', "Statut du colis #{$coli->code} mis à jour : {$coli->state}.");
    }

    /**
     * Suivi d'un colis (JSON) : historique des statuts, messages de notes et bons qui le contiennent.
     */
    public function suivi(Command $coli): JsonResponse
    {
        Gate::authorize('view', $coli);

        $coli->load(['historiques.auteur:id,nom_complet', 'noteMessages.auteur:id,nom_complet', 'statut:id,libelle,couleur,groupe']);

        $bons = collect([
            ['livraison', 'bon_livraison_command', 'bon_livraison_id', 'bons_livraison'],
            ['envoi', 'bon_envoi_command', 'bon_envoi_id', 'bons_envoi'],
            ['ramassage', 'bon_ramassage_lines', 'bon_ramassage_id', 'bon_ramassage'],
            ['retour', 'bon_retour_command', 'bon_retour_id', 'bons_retour'],
        ])->flatMap(fn ($t) => DB::table($t[1])->join($t[3], "{$t[3]}.id", '=', "{$t[1]}.{$t[2]}")
            ->where("{$t[1]}.command_id", $coli->id)
            ->get(["{$t[3]}.id", "{$t[3]}.code", "{$t[3]}.status", "{$t[3]}.legacy_dateadd_at"])
            ->map(fn ($b) => ['type' => $t[0], 'id' => $b->id, 'code' => $b->code, 'status' => $b->status, 'date' => $b->legacy_dateadd_at]))
            ->sortBy('date')->values();

        return response()->json([
            'colis' => [
                'id' => $coli->id, 'code' => $coli->code, 'state' => $coli->state, 'state_group' => $coli->state_group,
                'statut' => $coli->statut, 'note_brute' => $coli->note,
                'jalons' => [
                    'ramasse_at' => $coli->ramasse_at?->toDateTimeString(),
                    'expedie_at' => $coli->expedie_at?->toDateTimeString(),
                    'livre_at' => $coli->livre_at?->toDateTimeString(),
                    'retour_agence_at' => $coli->retour_agence_at?->toDateTimeString(),
                    'retour_recu_client_at' => $coli->retour_recu_client_at?->toDateTimeString(),
                ],
            ],
            'historique' => $coli->historiques->map(fn ($h) => [
                'id' => $h->id, 'de' => $h->from_state, 'vers' => $h->to_state, 'par' => $h->auteur?->nom_complet ?? $h->changed_by_nom,
                'type' => $h->changed_by_type, 'source' => $h->change_source, 'date' => $h->created_at?->toDateTimeString(),
            ]),
            'notes' => $coli->noteMessages->sortBy([['source', 'desc'], ['position', 'asc']])->values()->map(fn ($n) => [
                'id' => $n->id, 'role' => $n->role, 'auteur' => $n->auteur?->nom_complet ?? $n->auteur_nom, 'message' => $n->message,
                'source' => $n->source, 'date' => $n->created_at?->toDateTimeString(), 'date_exacte' => $n->date_exacte,
            ]),
            'bons' => $bons,
        ]);
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
     * Montant manuel saisi : pris en compte UNIQUEMENT pour l'administration.
     * Champ vide = pas de saisie (résolution automatique), jamais 0 implicite.
     */
    private function montantManuel(Request $request, array $validated): ?string
    {
        if (! $request->user()->isAdmin()) {
            return null;
        }

        $valeur = $validated['frais_livraison_client'] ?? null;

        return ($valeur === null || $valeur === '') ? null : (string) $valeur;
    }

    /** Commission livreur saisie : prise en compte UNIQUEMENT pour l'administration (vide = résolution). */
    private function commissionManuelle(Request $request, array $validated): ?string
    {
        if (! $request->user()->isAdmin()) {
            return null;
        }

        $valeur = $validated['frais_livraison_livreur'] ?? null;

        return ($valeur === null || $valeur === '') ? null : (string) $valeur;
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

