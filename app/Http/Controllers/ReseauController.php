<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\AgenceMembre;
use App\Models\Partenaire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Réseau » : agences, livreurs rattachés à une agence et partenaires du réseau national.
 */
class ReseauController extends Controller
{
    public const ONGLETS = ['agences', 'livreurs', 'partenaires'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Agence::class);

        $onglet = in_array($request->input('onglet'), self::ONGLETS, true) ? (string) $request->input('onglet') : 'agences';
        $search = trim((string) $request->input('search', ''));

        $bonsLivreur = DB::table('bons_livraison')->whereNotNull('livreur_id')->selectRaw('livreur_id, COUNT(*) AS n, MAX(legacy_dateadd_at) AS dernier')->groupBy('livreur_id')->get()->keyBy('livreur_id');
        $colisLivreur = DB::table('commands')->whereNotNull('livreur_id')->selectRaw('livreur_id, COUNT(*) AS n')->groupBy('livreur_id')->pluck('n', 'livreur_id');

        $agences = Agence::query()->with('ville:id,name_fr')->withCount(['membres as livreurs_count' => fn ($q) => $q->where('role', 'livreur')])
            ->orderByDesc('est_principale')->orderBy('nom')->get()
            ->map(fn (Agence $a) => [
                'id' => $a->id, 'code' => $a->code, 'nom' => $a->nom, 'ville' => $a->ville?->name_fr, 'ville_id' => $a->ville_id,
                'telephone' => $a->telephone, 'adresse' => $a->adresse, 'est_principale' => $a->est_principale, 'actif' => $a->actif,
                'livreurs' => $a->livreurs_count, 'colis' => DB::table('commands')->where('agence_id', $a->id)->count(),
            ]);

        $membres = AgenceMembre::query()->with(['user:id,nom_complet,telephone,deleted_at,active', 'agence:id,nom'])
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->withTrashed()->where(fn ($w) => $w->where('nom_complet', 'like', "%{$search}%")->orWhere('telephone', 'like', "%{$search}%"))))
            ->orderByDesc('actif')->get()
            ->map(fn (AgenceMembre $m) => [
                'id' => $m->id, 'agence' => $m->agence?->nom, 'role' => $m->role, 'actif' => $m->actif,
                'user_id' => $m->user_id, 'nom' => $m->user?->nom_complet, 'telephone' => $m->user?->telephone,
                'compte_archive' => $m->user?->deleted_at !== null,
                'bons' => (int) ($bonsLivreur[$m->user_id]->n ?? 0),
                'dernier_bon' => $bonsLivreur[$m->user_id]->dernier ?? null,
                'colis' => (int) ($colisLivreur[$m->user_id] ?? 0),
            ])->sortByDesc('bons')->values();

        $partenaires = Partenaire::query()->withTrashed()->with('compte:id,nom_complet')->withCount('bonsEnvoi')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('raison_sociale', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('raison_sociale')->get()
            ->map(fn (Partenaire $p) => [
                'id' => $p->id, 'code' => $p->code, 'raison_sociale' => $p->raison_sociale, 'ice' => $p->ice, 'telephone' => $p->telephone,
                'email' => $p->email, 'actif' => $p->actif, 'compte' => $p->compte?->nom_complet, 'notes' => $p->notes,
                'bons' => $p->bons_envoi_count, 'colis' => DB::table('commands')->where('partenaire_id', $p->id)->count(),
            ]);

        return Inertia::render('Reseau/Index', [
            'onglet' => $onglet,
            'agences' => $agences,
            'membres' => $membres,
            'partenaires' => $partenaires,
            'villes' => DB::table('villes')->whereNull('deleted_at')->orderBy('name_fr')->get(['id', 'name_fr']),
            'filters' => ['search' => $search],
            'can' => ['manage' => $request->user()->can('update', Agence::class)],
        ]);
    }

    public function updateAgence(Request $request, Agence $agence): RedirectResponse
    {
        Gate::authorize('update', $agence);
        $agence->update($request->validate([
            'nom' => ['required', 'string', 'max:120', Rule::unique('agences', 'nom')->ignore($agence->id)],
            'ville_id' => ['nullable', 'integer', 'exists:villes,id'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'actif' => ['required', 'boolean'],
        ]));

        return back()->with('success', "Agence « {$agence->nom} » enregistrée.");
    }

    public function updateMembre(Request $request, AgenceMembre $membre): RedirectResponse
    {
        Gate::authorize('update', Agence::class);
        $membre->update($request->validate([
            'role' => ['required', Rule::in(array_keys(AgenceMembre::ROLES))],
            'actif' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'Rattachement enregistré.');
    }

    public function updatePartenaire(Request $request, Partenaire $partenaire): RedirectResponse
    {
        Gate::authorize('update', $partenaire);
        $partenaire->update($request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'ice' => ['nullable', 'string', 'max:32'],
            'telephone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'actif' => ['required', 'boolean'],
        ]));

        return back()->with('success', "Partenaire « {$partenaire->raison_sociale} » enregistré.");
    }
}
