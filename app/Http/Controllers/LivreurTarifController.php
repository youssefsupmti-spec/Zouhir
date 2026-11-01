<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tarifs\Livreurs\Services\LivreurTarifEcriture;
use App\Domain\Tarifs\Livreurs\Services\LivreurTarifQuery;
use App\Http\Requests\Tarifs\LivreurTarifRequest;
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
 * Écran « Tarifs livreurs » (Sprint 29) : commissions VERSÉES aux livreurs, par livreur et
 * par ville (livreur_tarifs), comme WinningExpress. Pas de barème standard des commissions
 * (décision du propriétaire, 01/11/2026) : sans commission spécifique, la commission d'un
 * colis est « manquante ». Domaine distinct des frais facturés au marchand.
 */
class LivreurTarifController extends Controller
{
    public function __construct(
        private readonly LivreurTarifQuery $query,
        private readonly LivreurTarifEcriture $ecriture,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LivreurTarif::class);
        $user = $request->user();

        $zoneId = $this->idOuNull($request->input('zone_id'));
        $livreurId = $this->idOuNull($request->input('livreur_id'));

        $livreur = $livreurId === null ? null : User::drivers()->find($livreurId, ['id', 'nom_complet', 'telephone']);

        return Inertia::render('TarifsLivreurs/Index', [
            'livreurs' => $this->query->livreurs(),
            'stats' => $this->query->statistiques(),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'livreur' => $livreur === null ? null : ['id' => (int) $livreur->id, 'nom' => (string) $livreur->nom_complet, 'telephone' => $livreur->telephone],
            'grille' => $livreur === null ? null : $this->query->grilleDuLivreur((int) $livreur->id, $zoneId),
            'filters' => [
                'livreur_id' => $livreur === null ? 'all' : (string) $livreur->id,
                'zone_id' => $zoneId === null ? 'all' : (string) $zoneId,
            ],
            'can' => [
                'create' => $user->can('create', LivreurTarif::class),
                'update' => $user->can('update', LivreurTarif::class),
                'delete' => $user->can('delete', LivreurTarif::class),
            ],
        ]);
    }

    /** Référentiel du dialog d'affectation : villes ACTIVES et livreurs (JSON). */
    public function referentiel(): JsonResponse
    {
        Gate::authorize('viewAny', LivreurTarif::class);

        $villes = Ville::query()
            ->orderBy('name_fr')
            ->get(['id', 'name_fr', 'zone_id'])
            ->map(fn ($v) => [
                'id' => (int) $v->id,
                'nom' => (string) $v->name_fr,
                'zone_id' => $v->zone_id === null ? null : (int) $v->zone_id,
                'actuel' => null,
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

        return back()->with('success', "{$n} commission(s) supprimée(s) : les prochains colis de ces villes n'auront plus de commission pour ce livreur (« manquante »).");
    }

    public function restaurer(LivreurTarifRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $n = $this->ecriture->restaurerLot((int) $d['livreur_id'], $d['ids']);

        return back()->with('success', "{$n} commission(s) restaurée(s) avec leurs montants d'origine.");
    }

    private function idOuNull(mixed $valeur): ?int
    {
        return is_numeric($valeur) && (int) $valeur > 0 ? (int) $valeur : null;
    }
}
