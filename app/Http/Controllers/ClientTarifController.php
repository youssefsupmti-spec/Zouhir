<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tarifs\Clients\Services\ClientTarifEcriture;
use App\Domain\Tarifs\Clients\Services\ClientTarifQuery;
use App\Domain\Tarifs\Commun\Support\TarifResolu;
use App\Http\Requests\Tarifs\ModifierTarifsRequest;
use App\Http\Requests\Tarifs\NegocierTarifsRequest;
use App\Http\Requests\Tarifs\SupprimerTarifsRequest;
use App\Models\ClientTarif;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran « Tarifs Clients » (Sprint 28). Contrôleur léger : autorisation (ClientTarifPolicy),
 * validation (FormRequests), puis délégation aux services du domaine Tarifs.
 * Aucun calcul tarifaire ici.
 */
class ClientTarifController extends Controller
{
    public function __construct(
        private readonly ClientTarifQuery $query,
        private readonly ClientTarifEcriture $ecriture,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ClientTarif::class);
        $user = $request->user();

        $filtres = [
            'search' => trim((string) $request->input('search', '')),
            'ville' => trim((string) $request->input('ville', '')),
            'zone_id' => $this->idOuNull($request->input('zone_id')),
            'client_id' => $this->idOuNull($request->input('client_id')),
        ];
        $parPage = min(max((int) $request->input('per_page', 15), 5), 50);
        $onglet = $request->input('onglet') === 'corbeille' ? 'corbeille' : 'actifs';

        // Un compte marchand (permission déléguée par erreur) reste limité à sa propre grille.
        if ($user->isClient() && ! $user->isAdmin()) {
            $filtres['client_id'] = (int) $user->id;
        }

        return Inertia::render('TarifsClients/Index', [
            'clients' => $onglet === 'corbeille' ? null : $this->query->clients($filtres, $parPage),
            'corbeille' => $onglet === 'corbeille' ? $this->query->corbeille($filtres['search']) : null,
            'stats' => $this->query->statistiques(),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => [
                'onglet' => $onglet,
                'search' => $filtres['search'],
                'ville' => $filtres['ville'],
                'zone_id' => $filtres['zone_id'] === null ? 'all' : (string) $filtres['zone_id'],
                'client_id' => $filtres['client_id'] === null ? 'all' : (string) $filtres['client_id'],
                'per_page' => $parPage,
            ],
            'can' => [
                'create' => $user->can('create', ClientTarif::class),
                'update' => $user->can('update', ClientTarif::class),
                'delete' => $user->can('delete', ClientTarif::class),
            ],
        ]);
    }

    /** Grille d'un client, chargée à l'ouverture de son accordéon (JSON). */
    public function grille(User $client): JsonResponse
    {
        Gate::authorize('viewClient', [ClientTarif::class, $client]);
        abort_unless(User::clients()->whereKey($client->id)->exists(), 404);

        return response()->json([
            'client_id' => (int) $client->id,
            'zones' => $this->query->grilleDuClient((int) $client->id),
        ]);
    }

    /** Référentiels du modal de négociation : clients marchands, zones, villes + standard (JSON). */
    public function referentiel(): JsonResponse
    {
        Gate::authorize('create', ClientTarif::class);

        $clients = User::clients()
            ->with('businesses:id,user_id,name')
            ->orderBy('nom_complet')
            ->get(['id', 'nom_complet', 'email', 'store_name'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'nom' => (string) $u->nom_complet,
                'email' => $u->email,
                'enseignes' => $u->businesses->pluck('name')->filter()->values()->all()
                    ?: array_values(array_filter([$u->store_name])),
            ]);

        $villes = Ville::query()
            ->leftJoin('standard_tarifs', 'standard_tarifs.ville_id', '=', 'villes.id')
            ->orderBy('villes.name_fr')
            ->get(['villes.id', 'villes.name_fr', 'villes.zone_id', 'standard_tarifs.frais_livraison as standard'])
            ->map(fn ($v) => [
                'id' => (int) $v->id,
                'nom' => (string) $v->name_fr,
                'zone_id' => $v->zone_id === null ? null : (int) $v->zone_id,
                'standard' => $v->standard === null ? null : TarifResolu::montant($v->standard),
            ]);

        return response()->json([
            'clients' => $clients,
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'villes' => $villes,
        ]);
    }

    /** Aperçu AVANT écriture : nouveau / actif / supprimé pour chaque ville visée (aucune écriture). */
    public function apercu(NegocierTarifsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $villeIds = $data['mode'] === 'zone'
            ? Ville::query()->where('zone_id', (int) $data['zone_id'])->pluck('id')->all()
            : ($data['ville_ids'] ?? []);

        $etat = $this->ecriture->apercu((int) $data['client_id'], $villeIds);
        $compte = ['nouveau' => 0, 'actif' => 0, 'supprime' => 0];
        foreach ($etat as $e) {
            $compte[$e['statut']]++;
        }

        return response()->json([
            'villes' => array_values($etat),
            'compte' => $compte,
            'total' => count($etat),
        ]);
    }

    public function store(NegocierTarifsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $r = $this->ecriture->ajouter(
            (int) $data['client_id'],
            $data['mode'] === 'villes' ? ($data['ville_ids'] ?? []) : [],
            $data['mode'] === 'zone' ? (int) $data['zone_id'] : null,
            ['livraison' => $data['livraison'], 'refus' => $data['refus'], 'retour' => $data['retour']],
            $data['existants'],
            (bool) $data['restaurer'],
        );

        return back()->with('success', sprintf(
            'Tarifs enregistrés : %d créé(s), %d mis à jour, %d restauré(s), %d ignoré(s).',
            $r['crees'], $r['mises_a_jour'], $r['restaurees'], $r['ignorees'],
        ));
    }

    public function update(ModifierTarifsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $n = $this->ecriture->modifierLot((int) $data['client_id'], $data['lignes']);

        return back()->with('success', $n === 0
            ? 'Aucune modification : les montants étaient identiques.'
            : "{$n} tarif(s) modifié(s). Les colis existants conservent leur tarif figé.");
    }

    public function destroy(SupprimerTarifsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $n = $this->ecriture->supprimerLot((int) $data['client_id'], $data['ids']);

        return back()->with('success', "{$n} tarif(s) supprimé(s) : ces villes repassent au tarif standard pour les prochains colis.");
    }

    public function restaurerCorbeille(Request $request): RedirectResponse
    {
        Gate::authorize('delete', ClientTarif::class);
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:1000'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $n = $this->ecriture->restaurerCorbeille($ids);

        return back()->with('success', "{$n} tarif(s) restauré(s) avec leurs montants d'origine.");
    }

    public function purgerCorbeille(Request $request): RedirectResponse
    {
        Gate::authorize('delete', ClientTarif::class);
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:1000'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $n = $this->ecriture->purgerCorbeille($ids);

        return back()->with('success', "{$n} tarif(s) supprimé(s) définitivement. Aucun colis n'est modifié.");
    }

    private function idOuNull(mixed $valeur): ?int
    {
        return is_numeric($valeur) && (int) $valeur > 0 ? (int) $valeur : null;
    }
}
