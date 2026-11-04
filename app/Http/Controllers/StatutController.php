<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\StatutsColis\ClassificationEtats;
use App\Models\EtatGroupe;
use App\Models\Statut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Référentiel des statuts colis : groupe, couleur, activation. */
class StatutController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Statut::class);

        $origine = array_key_exists((string) $request->input('origine'), Statut::ORIGINES) ? (string) $request->input('origine') : '';
        $groupes = ClassificationEtats::groupes()->pluck('nom', 'code');
        $groupe = $groupes->has((string) $request->input('groupe')) ? (string) $request->input('groupe') : '';
        $search = trim((string) $request->input('search', ''));

        $colis = DB::table('commands')->whereNotNull('statut_id')->selectRaw('statut_id, COUNT(*) AS n')->groupBy('statut_id')->pluck('n', 'statut_id');
        $historique = DB::table('command_status_histories')->whereNotNull('to_statut_id')->selectRaw('to_statut_id, COUNT(*) AS n')->groupBy('to_statut_id')->pluck('n', 'to_statut_id');

        $statuts = Statut::query()
            ->when($origine !== '', fn ($q) => $q->where('origine', $origine))
            ->when($groupe !== '', fn ($q) => $q->where('groupe', $groupe))
            ->when($search !== '', fn ($q) => $q->where('libelle', 'like', "%{$search}%"))
            ->orderBy('ordre')->orderBy('id')->get()
            ->map(fn (Statut $s) => [
                'id' => $s->id,
                'libelle' => $s->libelle,
                'couleur' => $s->couleur,
                'groupe' => $s->groupe,
                'origine' => $s->origine,
                'actif' => $s->actif,
                'kpi_legacy' => $s->kpi_legacy,
                'acteurs_legacy' => $s->acteurs_legacy,
                'colis' => (int) ($colis[$s->id] ?? 0),
                'historique' => (int) ($historique[$s->id] ?? 0),
            ]);

        return Inertia::render('Statuts/Index', [
            'statuts' => $statuts,
            'groupes' => $groupes,
            'origines' => Statut::ORIGINES,
            'stats' => [
                'total' => Statut::count(),
                'actifs' => Statut::where('actif', true)->count(),
                'hors_referentiel' => Statut::where('origine', 'hors_referentiel')->count(),
                'colis_sans_statut' => DB::table('commands')->whereNull('statut_id')->count(),
                'par_groupe' => DB::table('commands')->selectRaw('state_group, COUNT(*) AS n')->groupBy('state_group')->pluck('n', 'state_group'),
            ],
            'filters' => ['origine' => $origine, 'groupe' => $groupe, 'search' => $search],
            'can' => ['manage' => $request->user()->can('update', Statut::class)],
        ]);
    }

    /**
     * Le groupe d'un statut est recopié sur ses colis (commands.state_group) : les compteurs
     * « livrés / retours / en cours » restent cohérents avec le référentiel.
     */
    public function update(Request $request, Statut $statut): RedirectResponse
    {
        Gate::authorize('update', $statut);

        $data = $request->validate([
            'groupe' => ['required', Rule::in(ClassificationEtats::groupes()->pluck('code')->all())],
            'couleur' => ['nullable', 'string', 'max:30'],
            'actif' => ['required', 'boolean'],
        ]);

        $colis = DB::transaction(function () use ($statut, $data) {
            $statut->update(['couleur' => $data['couleur'] ?? null, 'actif' => $data['actif']]);
            $groupe = EtatGroupe::query()->where('axe', EtatGroupe::AXE_FLUX)->where('code', $data['groupe'])->firstOrFail();

            return ClassificationEtats::rattacher($groupe, [$statut->id]);
        });

        return back()->with('success', "Statut « {$statut->libelle} » enregistré".($colis > 0 ? " — groupe recopié sur {$colis} colis." : '.'));
    }
}
