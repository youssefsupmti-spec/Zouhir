<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tarifs\Livreurs\Services\LivreurStandardGrille;
use App\Domain\Tarifs\Livreurs\Services\LivreurTarifEcriture;
use App\Domain\Tarifs\Livreurs\Services\LivreurTarifQuery;
use App\Http\Requests\Tarifs\LivreurTarifRequest;
use App\Models\LivreurStandardTarif;
use App\Models\LivreurTarif;
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
 * Écran « Tarifs livreurs » (Sprint 29) : commissions VERSÉES aux livreurs.
 *  - onglet « Commissions spécifiques » : grille d'un livreur (livreur_tarifs) ;
 *  - onglet « Barème standard » : commission par défaut par ville (livreur_standard_tarifs).
 * Domaine distinct des frais facturés au marchand (standard_tarifs / client_tarifs).
 */
class LivreurTarifController extends Controller
{
    public function __construct(
        private readonly LivreurTarifQuery $query,
        private readonly LivreurTarifEcriture $ecriture,
        private readonly LivreurStandardGrille $standard,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LivreurTarif::class);
        $user = $request->user();

        $onglet = $request->input('onglet') === 'standard' ? 'standard' : 'specifiques';
        $statut = (string) $request->input('statut', 'tous');
        $zoneId = $this->idOuNull($request->input('zone_id'));
        $livreurId = $this->idOuNull($request->input('livreur_id'));
        $search = trim((string) $request->input('search', ''));
        $parPage = min(max((int) $request->input('per_page', 50), 10), 200);

        $livreur = $livreurId === null ? null : User::drivers()->find($livreurId, ['id', 'nom_complet', 'telephone']);

        return Inertia::render('TarifsLivreurs/Index', [
            'livreurs' => $this->query->livreurs(),
            'stats' => $this->query->statistiques(),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'livreur' => $livreur === null ? null : ['id' => (int) $livreur->id, 'nom' => (string) $livreur->nom_complet, 'telephone' => $livreur->telephone],
            'grille' => $onglet === 'specifiques' && $livreur !== null
                ? $this->query->grilleDuLivreur((int) $livreur->id, $zoneId)
                : null,
            'bareme' => $onglet === 'standard'
                ? $this->standard->villes(['search' => $search, 'zone_id' => $zoneId, 'statut' => in_array($statut, ['tous', 'tarifees', 'sans'], true) ? $statut : 'tous'], $parPage)
                : null,
            'baremeStats' => $this->standard->statistiques(),
            'filters' => [
                'onglet' => $onglet,
                'livreur_id' => $livreur === null ? 'all' : (string) $livreur->id,
                'zone_id' => $zoneId === null ? 'all' : (string) $zoneId,
                'search' => $search,
                'statut' => $statut,
                'per_page' => $parPage,
            ],
            'can' => [
                'create' => $user->can('create', LivreurTarif::class),
                'update' => $user->can('update', LivreurTarif::class),
                'delete' => $user->can('delete', LivreurTarif::class),
                'standard' => $user->can('update', LivreurStandardTarif::class),
            ],
        ]);
    }

    /** Référentiel des dialogs : villes ACTIVES + commission standard livreur (JSON, une requête). */
    public function referentiel(): JsonResponse
    {
        Gate::authorize('viewAny', LivreurTarif::class);

        $villes = Ville::query()
            ->leftJoin('livreur_standard_tarifs', 'livreur_standard_tarifs.ville_id', '=', 'villes.id')
            ->orderBy('villes.name_fr')
            ->get(['villes.id', 'villes.name_fr', 'villes.zone_id', 'livreur_standard_tarifs.commission_livraison as standard'])
            ->map(fn ($v) => [
                'id' => (int) $v->id,
                'nom' => (string) $v->name_fr,
                'zone_id' => $v->zone_id === null ? null : (int) $v->zone_id,
                'actuel' => $v->standard === null ? null : number_format((float) $v->standard, 2, '.', ''),
            ]);

        return response()->json([
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'villes' => $villes,
            'livreurs' => $this->query->livreurs(),
        ]);
    }

    public function apercu(LivreurTarifRequest $request): JsonResponse
    {
        $d = $request->validated();
        $villes = $this->ecriture->apercu((int) $d['livreur_id'], $d['ville_ids'] ?? [], isset($d['zone_id']) ? (int) $d['zone_id'] : null);
        $villes = array_values(array_filter($villes, fn ($v) => ! in_array($v['ville_id'], array_map('intval', $d['exclusions'] ?? []), true)));

        $compte = ['nouveau' => 0, 'actif' => 0, 'supprime' => 0];
        foreach ($villes as $v) {
            $compte[$v['statut']]++;
        }

        return response()->json(['villes' => $villes, 'compte' => $compte, 'total' => count($villes)]);
    }

    public function store(LivreurTarifRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $villeIds = $d['ville_ids'] ?? [];
        $zoneId = isset($d['zone_id']) ? (int) $d['zone_id'] : null;

        // Zone moins exclusions : convertie en liste explicite de villes actives.
        if ($zoneId !== null && ! empty($d['exclusions'])) {
            $villeIds = Ville::query()->where('zone_id', $zoneId)->whereKeyNot(array_map('intval', $d['exclusions']))->pluck('id')->all();
            $zoneId = null;
        }

        $r = $this->ecriture->ajouter((int) $d['livreur_id'], $villeIds, $zoneId,
            ['livraison' => $d['livraison'], 'refus' => $d['refus']], $d['existants'], (bool) $d['restaurer']);

        return back()->with('success', sprintf(
            'Commissions : %d créée(s), %d mise(s) à jour, %d restaurée(s), %d ignorée(s).',
            $r['crees'], $r['mises_a_jour'], $r['restaurees'], $r['ignorees'],
        ));
    }

    public function update(LivreurTarifRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $n = $this->ecriture->modifierLot((int) $d['livreur_id'], $d['lignes']);

        return back()->with('success', $n === 0
            ? 'Aucune modification : les montants étaient identiques.'
            : "{$n} commission(s) modifiée(s). Les colis existants conservent leur commission figée.");
    }

    public function destroy(LivreurTarifRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $n = $this->ecriture->supprimerLot((int) $d['livreur_id'], $d['ids']);

        return back()->with('success', "{$n} commission(s) supprimée(s) : ces villes repassent au barème standard livreur pour les prochains colis.");
    }

    public function restaurer(LivreurTarifRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $n = $this->ecriture->restaurerLot((int) $d['livreur_id'], $d['ids']);

        return back()->with('success', "{$n} commission(s) restaurée(s) avec leurs montants d'origine.");
    }

    public function standardApercu(LivreurTarifRequest $request): JsonResponse
    {
        $d = $request->validated();
        $villes = $this->standard->apercu($d['ville_ids'] ?? [], isset($d['zone_id']) ? (int) $d['zone_id'] : null, $d['exclusions'] ?? []);

        return response()->json([
            'villes' => $villes,
            'compte' => [
                'nouveau' => count(array_filter($villes, fn ($v) => $v['statut'] === 'nouveau')),
                'existant' => count(array_filter($villes, fn ($v) => $v['statut'] === 'existant')),
            ],
            'total' => count($villes),
        ]);
    }

    public function standardAppliquer(LivreurTarifRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $r = $this->standard->appliquer($d['ville_ids'] ?? [], isset($d['zone_id']) ? (int) $d['zone_id'] : null,
            $d['exclusions'] ?? [], ['livraison' => $d['livraison'], 'refus' => $d['refus']], $d['existants']);

        return back()->with('success', sprintf('Barème standard livreur : %d créé(s), %d mis à jour, %d ignoré(s).', $r['crees'], $r['mises_a_jour'], $r['ignorees']));
    }

    public function standardLot(LivreurTarifRequest $request): RedirectResponse
    {
        $r = $this->standard->enregistrerLot($request->validated('lignes'));

        return back()->with('success', sprintf('Barème standard livreur : %d créé(s), %d modifié(s).', $r['crees'], $r['modifiees']));
    }

    private function idOuNull(mixed $valeur): ?int
    {
        return is_numeric($valeur) && (int) $valeur > 0 ? (int) $valeur : null;
    }
}
