<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Zones\Services\ZoneEcriture;
use App\Domain\Zones\Services\ZoneQuery;
use App\Http\Requests\Zones\VillesZoneRequest;
use App\Http\Requests\Zones\ZoneRequest;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran « Zones » (rapport #39, structure WinningExpress) : liste des zones avec aperçu des
 * villes, page d'édition à deux panneaux (villes disponibles ↔ villes de la zone).
 * Contrôleur léger : autorisation (ZonePolicy), validation (FormRequests), services Domain.
 */
class ZoneController extends Controller
{
    public function __construct(
        private readonly ZoneQuery $query,
        private readonly ZoneEcriture $ecriture,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Zone::class);

        $onglet = in_array($request->input('onglet'), ZoneQuery::ONGLETS, true) ? (string) $request->input('onglet') : 'toutes';
        $search = trim((string) $request->input('search', ''));
        $parPage = min(max((int) $request->input('per_page', 25), 10), 100);

        return Inertia::render('Zones/Index', [
            'zones' => $this->query->zones(['onglet' => $onglet, 'search' => $search], $parPage),
            'stats' => $this->query->statistiques(),
            'filters' => ['onglet' => $onglet, 'search' => $search, 'per_page' => $parPage],
            'can' => ['manage' => $request->user()->can('create', Zone::class)],
        ]);
    }

    public function edit(Request $request, Zone $zone): Response
    {
        Gate::authorize('update', $zone);

        return Inertia::render('Zones/Edit', [
            'zone' => [
                'id' => $zone->id, 'name' => $zone->name, 'code' => $zone->code,
                'type' => $zone->type->value, 'description' => $zone->description,
            ],
            'stats' => [
                'villes' => $zone->villes()->count(),
                'sans_zone' => $this->query->statistiques()['sans_zone'],
            ],
        ]);
    }

    /** Panneau de la page d'édition (JSON paginé) : `panneau=zone|disponibles`. */
    public function villes(Request $request, Zone $zone): JsonResponse
    {
        Gate::authorize('update', $zone);

        return response()->json($this->query->villes($zone, [
            'panneau' => $request->input('panneau') === 'zone' ? 'zone' : 'disponibles',
            'search' => trim((string) $request->input('search', '')),
            'sans_zone' => $request->boolean('sans_zone'),
        ], min(max((int) $request->input('per_page', 25), 10), 100)));
    }

    public function store(ZoneRequest $request): RedirectResponse
    {
        $zone = $this->ecriture->creer($request->validated());

        return redirect("/zones/{$zone->id}/edit")->with('success', "Zone {$zone->code} créée : rattachez maintenant ses villes.");
    }

    public function update(ZoneRequest $request, Zone $zone): RedirectResponse
    {
        $this->ecriture->modifier($zone, $request->validated());

        return back()->with('success', 'Zone mise à jour.');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        Gate::authorize('delete', $zone);
        $n = $this->ecriture->supprimer($zone);

        return back()->with('success', "Zone {$zone->code} mise à la corbeille ; {$n} ville(s) détachée(s), désormais sans zone.");
    }

    public function restaurer(int $zone): RedirectResponse
    {
        $modele = Zone::onlyTrashed()->findOrFail($zone);
        Gate::authorize('restore', $modele);
        $this->ecriture->restaurer($modele);

        return back()->with('success', "Zone {$modele->code} restaurée (sans villes : rattachez-les depuis sa page).");
    }

    public function rattacher(VillesZoneRequest $request, Zone $zone): JsonResponse
    {
        return response()->json(['modifiees' => $this->ecriture->rattacher($zone, $request->ids())]);
    }

    public function detacher(VillesZoneRequest $request, Zone $zone): JsonResponse
    {
        return response()->json(['modifiees' => $this->ecriture->detacher($zone, $request->ids())]);
    }
}
