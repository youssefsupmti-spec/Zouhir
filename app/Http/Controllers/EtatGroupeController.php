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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Classification des états : groupes d'état (etat_groupes) et rattachement des états
 * (etat_groupe_etats). Les groupes actifs de l'axe « flux » sont les onglets de la page Colis.
 */
class EtatGroupeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Statut::class);

        $onglet = in_array($request->input('onglet'), ['groupes', 'etats'], true) ? (string) $request->input('onglet') : 'groupes';
        $colisParGroupe = DB::table('commands')->selectRaw('state_group, COUNT(*) AS n')->groupBy('state_group')->pluck('n', 'state_group');
        $colisParStatut = DB::table('commands')->whereNotNull('statut_id')->selectRaw('statut_id, COUNT(*) AS n')->groupBy('statut_id')->pluck('n', 'statut_id');
        $rattachement = DB::table('etat_groupe_etats')->where('axe', EtatGroupe::AXE_FLUX)->pluck('groupe_id', 'etat_id');

        $groupes = ClassificationEtats::groupes()->loadCount('rattachements')->map(fn (EtatGroupe $g) => [
            'id' => $g->id, 'code' => $g->code, 'nom' => $g->nom, 'description' => $g->description, 'couleur' => $g->couleur,
            'sort_order' => $g->sort_order, 'actif' => $g->actif, 'suivi_actif' => $g->suivi_actif, 'suivi_planifie' => $g->suivi_planifie,
            'systeme' => $g->estSysteme(), 'etats' => $g->rattachements_count, 'colis' => (int) ($colisParGroupe[$g->code] ?? 0),
        ]);

        $etats = Statut::query()->orderBy('ordre')->orderBy('id')->get()->map(fn (Statut $s) => [
            'id' => $s->id, 'libelle' => $s->libelle, 'couleur' => $s->couleur, 'actif' => $s->actif, 'origine' => $s->origine,
            'groupe_id' => isset($rattachement[$s->id]) ? (int) $rattachement[$s->id] : null,
            'colis' => (int) ($colisParStatut[$s->id] ?? 0),
        ]);

        return Inertia::render('EtatGroupes/Index', [
            'onglet' => $onglet,
            'groupes' => $groupes,
            'etats' => $etats,
            'stats' => [
                'groupes' => $groupes->count(),
                'actifs' => $groupes->where('actif', true)->count(),
                'etats' => $etats->count(),
                'non_classes' => $etats->whereNull('groupe_id')->count(),
            ],
            'can' => ['manage' => $request->user()->can('update', Statut::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('update', Statut::class);
        $data = $this->valider($request);
        $data['code'] = Str::slug($request->input('code') ?: $data['nom'], '_');

        $request->merge(['code' => $data['code']])->validate([
            'code' => ['required', 'max:40', Rule::unique('etat_groupes', 'code')->where('axe', EtatGroupe::AXE_FLUX)],
        ], ['code.unique' => 'Ce code de groupe existe déjà.']);

        EtatGroupe::create($data + ['axe' => EtatGroupe::AXE_FLUX]);

        return back()->with('success', "Groupe « {$data['nom']} » créé.");
    }

    public function update(Request $request, EtatGroupe $groupe): RedirectResponse
    {
        Gate::authorize('update', Statut::class);
        $groupe->update($this->valider($request));

        return back()->with('success', "Groupe « {$groupe->nom} » enregistré.");
    }

    public function destroy(EtatGroupe $groupe): RedirectResponse
    {
        Gate::authorize('update', Statut::class);

        if ($groupe->estSysteme()) {
            return back()->with('error', "Le groupe « {$groupe->nom} » est utilisé par les règles métier : désactivez-le plutôt.");
        }
        if ($groupe->rattachements()->exists()) {
            return back()->with('error', "Le groupe « {$groupe->nom} » contient encore des états : rattachez-les d’abord à un autre groupe.");
        }
        $groupe->delete();

        return back()->with('success', "Groupe « {$groupe->nom} » supprimé.");
    }

    /** Rattacher une sélection d'états à un groupe (ils quittent leur groupe actuel). */
    public function rattacher(Request $request, EtatGroupe $groupe): RedirectResponse
    {
        Gate::authorize('update', Statut::class);
        $data = $request->validate(['etat_ids' => ['required', 'array', 'min:1'], 'etat_ids.*' => ['integer', 'exists:statuts,id']]);

        $colis = ClassificationEtats::rattacher($groupe, $data['etat_ids']);

        return back()->with('success', count($data['etat_ids'])." état(s) rattaché(s) à « {$groupe->nom} »".($colis > 0 ? " — {$colis} colis reclassés." : '.'));
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'couleur' => ['nullable', 'string', 'max:32', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'actif' => ['required', 'boolean'],
            'suivi_actif' => ['required', 'boolean'],
            'suivi_planifie' => ['required', 'boolean'],
        ], ['couleur.regex' => 'Couleur au format #RRGGBB.']);
    }
}
