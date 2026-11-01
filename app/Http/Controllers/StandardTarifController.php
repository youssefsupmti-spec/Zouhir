<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tarifs\Standard\Services\StandardTarifGrille;
use App\Http\Requests\Tarifs\AppliquerStandardRequest;
use App\Http\Requests\Tarifs\EnregistrerStandardLotRequest;
use App\Models\StandardTarif;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran « Tarifs de livraison » (Sprint 29) : barème standard facturé aux marchands.
 * Contrôleur léger : autorisation (StandardTarifPolicy), validation (FormRequests),
 * délégation à StandardTarifGrille. Aucun calcul tarifaire ici ni dans React.
 */
class StandardTarifController extends Controller
{
    public function __construct(private readonly StandardTarifGrille $grille) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StandardTarif::class);
        $user = $request->user();

        $statut = (string) $request->input('statut', 'tous');
        $filtres = [
            'search' => trim((string) $request->input('search', '')),
            'zone_id' => $this->idOuNull($request->input('zone_id')),
            'statut' => in_array($statut, ['tous', 'tarifees', 'sans'], true) ? $statut : 'tous',
        ];
        $parPage = min(max((int) $request->input('per_page', 50), 10), 200);

        return Inertia::render('TarifsStandard/Index', [
            'villes' => $this->grille->villes($filtres, $parPage),
            'stats' => $this->grille->statistiques(),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => [
                'search' => $filtres['search'],
                'zone_id' => $filtres['zone_id'] === null ? 'all' : (string) $filtres['zone_id'],
                'statut' => $filtres['statut'],
                'per_page' => $parPage,
            ],
            'can' => [
                'create' => $user->can('create', StandardTarif::class),
                'update' => $user->can('update', StandardTarif::class),
            ],
        ]);
    }

    /** Référentiel du dialog d'ajout : villes ACTIVES + tarif standard actuel (JSON, une requête). */
    public function referentiel(): JsonResponse
    {
        Gate::authorize('create', StandardTarif::class);

        $villes = Ville::query()
            ->leftJoin('standard_tarifs', 'standard_tarifs.ville_id', '=', 'villes.id')
            ->orderBy('villes.name_fr')
            ->get(['villes.id', 'villes.name_fr', 'villes.zone_id', 'standard_tarifs.frais_livraison as livraison'])
            ->map(fn ($v) => [
                'id' => (int) $v->id,
                'nom' => (string) $v->name_fr,
                'zone_id' => $v->zone_id === null ? null : (int) $v->zone_id,
                'actuel' => $v->livraison === null ? null : number_format((float) $v->livraison, 2, '.', ''),
            ]);

        return response()->json([
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'villes' => $villes,
        ]);
    }

    /** Aperçu AVANT écriture (villes ou zone moins exclusions) : nouveau / existant. */
    public function apercu(AppliquerStandardRequest $request): JsonResponse
    {
        $d = $request->validated();
        $villes = $this->grille->apercu($d['ville_ids'] ?? [], isset($d['zone_id']) ? (int) $d['zone_id'] : null, $d['exclusions'] ?? []);

        return response()->json([
            'villes' => $villes,
            'compte' => [
                'nouveau' => count(array_filter($villes, fn ($v) => $v['statut'] === 'nouveau')),
                'existant' => count(array_filter($villes, fn ($v) => $v['statut'] === 'existant')),
            ],
            'total' => count($villes),
        ]);
    }

    /** Ajout en masse / application à une zone, avec choix explicite pour les existants. */
    public function store(AppliquerStandardRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $r = $this->grille->appliquer(
            $d['ville_ids'] ?? [],
            isset($d['zone_id']) ? (int) $d['zone_id'] : null,
            $d['exclusions'] ?? [],
            ['livraison' => $d['livraison'], 'refus' => $d['refus'], 'retour' => $d['retour'], 'delai' => $d['delai'] ?? null],
            $d['existants'],
        );

        return back()->with('success', sprintf(
            'Tarifs standards : %d créé(s), %d mis à jour, %d ignoré(s). Les colis existants conservent leurs frais figés.',
            $r['crees'], $r['mises_a_jour'], $r['ignorees'],
        ));
    }

    /** Édition inline : seules les lignes modifiées sont envoyées ; erreurs rendues par ligne. */
    public function lot(EnregistrerStandardLotRequest $request): RedirectResponse
    {
        $r = $this->grille->enregistrerLot($request->validated('lignes'));

        return back()->with('success', sprintf('%d tarif(s) créé(s), %d modifié(s).', $r['crees'], $r['modifiees']));
    }

    private function idOuNull(mixed $valeur): ?int
    {
        return is_numeric($valeur) && (int) $valeur > 0 ? (int) $valeur : null;
    }
}
