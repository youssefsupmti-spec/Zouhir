<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BonEnvoi;
use App\Models\BonLivraison;
use App\Models\BonRamassage;
use App\Models\BonRetour;
use App\Models\RepriseAnomalie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bons du circuit colis, réunis sur une seule page à onglets (livraison, envoi, ramassage, retour).
 * Lecture : la création et le scan des bons viendront avec le module opérationnel.
 */
class BonController extends Controller
{
    /** type => [modèle, libellé, domaine d'anomalies, statuts] */
    public const TYPES = [
        'livraison' => [BonLivraison::class, 'Bons de livraison', 'bons_livraison'],
        'envoi' => [BonEnvoi::class, 'Bons d’envoi', 'bons_envoi'],
        'ramassage' => [BonRamassage::class, 'Bons de ramassage', 'bons_ramassage'],
        'retour' => [BonRetour::class, 'Bons de retour', 'bons_retour'],
    ];

    /**
     * Une page par liste (comme WinningExpress) : url => [type, sous-type de retour, titre, description].
     */
    public const PAGES = [
        'bons-livraison' => ['livraison', null, 'Bons de livraison', 'Tournées des livreurs de l’agence (dernier kilomètre).'],
        'bons-envoi' => ['envoi', null, 'Bons d’envoi', 'Colis remis au réseau partenaire pour les autres villes du Royaume.'],
        'bons-ramassage' => ['ramassage', null, 'Bons de ramassage', 'Colis des marchands entrés au hub.'],
        'bons-retour' => ['retour', null, 'Bons de retour', 'Colis restitués aux marchands ou déchargés par les livreurs à l’agence.'],
    ];

    /** Page d'un bon → URL de sa liste. */
    public static function urlListe(string $type, ?string $sousType = null): string
    {
        foreach (self::PAGES as $url => [$t, $s]) {
            if ($t === $type && ($s === null || $s === $sousType)) {
                return '/'.$url;
            }
        }

        return '/bons-livraison';
    }

    public function index(Request $request, string $page = 'bons-livraison'): Response
    {
        Gate::authorize('viewAny', BonLivraison::class);

        abort_unless(array_key_exists($page, self::PAGES), 404);
        [$type, $sousType, $titre, $description] = self::PAGES[$page];
        // Bons de retour : onglets « Client » / « Livreur » sur la même page.
        $onglet = $type === 'retour' && in_array($request->input('sous_type'), ['client', 'livreur'], true) ? (string) $request->input('sous_type') : null;
        $sousType ??= $onglet;
        [$modele] = self::TYPES[$type];
        $restreindre = fn ($w) => $w->when($sousType !== null, fn ($x) => $x->where('type', $sousType));
        $statut = (string) $request->input('statut', '');
        $search = trim((string) $request->input('search', ''));
        $parPage = min(max((int) $request->input('per_page', 25), 10), 100);
        $corbeille = $request->boolean('corbeille');

        /** @var Builder $q */
        $q = $modele::query()->tap($restreindre)
            ->when($corbeille, fn ($w) => $w->onlyTrashed())
            ->when(array_key_exists($statut, $modele::STATUTS), fn ($w) => $w->where('status', $statut))
            ->when($search !== '', fn ($w) => $w->where(function ($s) use ($search, $type) {
                $s->where('code', 'like', "%{$search}%");
                if (in_array($type, ['livraison', 'envoi', 'retour'], true)) {
                    $s->orWhere('livreur_nom', 'like', "%{$search}%");
                }
                if (in_array($type, ['ramassage', 'retour'], true)) {
                    $s->orWhere('business_name', 'like', "%{$search}%");
                }
                if ($type === 'ramassage') {
                    $s->orWhere('nom_cliente', 'like', "%{$search}%");
                }
                $s->orWhereHas('lignes', fn ($l) => $l->where($type === 'ramassage' ? 'code' : 'command_code', 'like', "%{$search}%"));
            }));

        $bons = $q->orderByDesc('legacy_dateadd_at')->orderByDesc('id')->paginate($parPage)->withQueryString()
            ->through(fn (Model $b) => $this->ligne($type, $b));

        return Inertia::render('Bons/Index', [
            'type' => $type,
            'page' => ['url' => '/'.$page, 'titre' => $titre, 'description' => $description, 'sous_type' => $sousType],
            'sousTypes' => $type === 'retour' ? [
                'tous' => BonRetour::query()->count(),
                'client' => BonRetour::query()->where('type', 'client')->count(),
                'livreur' => BonRetour::query()->where('type', 'livreur')->count(),
            ] : null,
            'bons' => $bons,
            'stats' => $this->stats($modele, $restreindre),
            'statuts' => $modele::STATUTS,
            'filters' => ['statut' => $statut, 'search' => $search, 'per_page' => $parPage, 'corbeille' => $corbeille],
        ]);
    }

    /**
     * Bon imprimable (A4) : en-tête de l'agence, informations, code-barres du bon, tableau des colis,
     * total CRBT et cases de signature. Affiché dans une modale (iframe) ; « Enregistrer en PDF »
     * passe par l'impression du navigateur — aucun moteur PDF à installer sur l'hébergement.
     */
    public function imprimer(string $type, int $bon): \Illuminate\Contracts\View\View
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        [$modele, $libelle] = self::TYPES[$type];
        Gate::authorize('view', $modele);

        /** @var Model $b */
        $b = $modele::withTrashed()->with('lignes')->findOrFail($bon);
        $colis = \App\Models\Command::withTrashed()
            ->whereIn('id', $b->lignes->pluck('command_id')->filter())
            ->get(['id', 'code', 'produit', 'quantite', 'destinataire_nom', 'destinataire_telephone', 'destinataire_adresse', 'destinataire_ville', 'prix_brut', 'state'])
            ->keyBy('id');

        $lignes = $b->lignes->map(function ($l) use ($colis) {
            $c = $l->command_id !== null ? ($colis[$l->command_id] ?? null) : null;

            return [
                'sequence' => $l->sequence,
                'code' => $l->command_code ?? $l->code ?? $c?->code,
                'destinataire' => $l->destinataire_nom ?? $l->nom_destinataire ?? $c?->destinataire_nom,
                'telephone' => $l->destinataire_telephone ?? $l->telephone ?? $c?->destinataire_telephone,
                'ville' => $l->destinataire_ville ?? $l->ville ?? $c?->destinataire_ville,
                'adresse' => $c?->destinataire_adresse,
                'produit' => $c ? trim(($c->produit ?? '').($c->quantite > 1 ? " (×{$c->quantite})" : '')) : ($l->produit ?? null),
                'montant' => (float) ($l->prix_brut ?? $l->prix ?? $c?->prix_brut ?? 0),
                'absent' => $l->command_id === null,
            ];
        });

        $titre = match ($type) {
            'livraison' => 'Bon de livraison',
            'envoi' => 'Bon d’envoi',
            'ramassage' => 'Bon de ramassage',
            'retour' => $b->type === 'livreur' ? 'Bon de retour livreur' : 'Bon de retour client',
        };
        [$roleTiers, $tiers, $telTiers] = match ($type) {
            'livraison' => ['Livreur', $b->livreur_nom, $b->livreur_telephone],
            'envoi' => ['Partenaire', $b->hub ?? $b->livreur_nom, $b->livreur_telephone],
            'ramassage' => ['Marchand', trim(($b->business_name ?? '').($b->nom_cliente ? ' — '.$b->nom_cliente : ''), ' —'), null],
            'retour' => $b->type === 'livreur' ? ['Livreur', $b->livreur_nom, null] : ['Marchand', $b->business_name, null],
        };

        return view('print.bon', [
            'type' => $type,
            'titre' => $titre,
            'bon' => $b,
            'statut' => $modele::STATUTS[$b->status] ?? $b->status,
            'lignes' => $lignes,
            'total' => round((float) $lignes->sum('montant'), 2),
            'roleTiers' => $roleTiers,
            'tiers' => $tiers ?: '—',
            'telTiers' => $telTiers,
            'agence' => \App\Models\Agence::principale(),
            'codeBarres' => \App\Domain\Documents\Code128::svg((string) $b->code),
            'imprimePar' => request()->user()?->nom_complet,
        ]);
    }

    public function show(string $type, int $bon): Response
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        [$modele, $libelle, $domaine] = self::TYPES[$type];
        Gate::authorize('view', $modele);

        /** @var Model $b */
        $b = $modele::withTrashed()->with(['lignes.command:id,code,state,state_group,deleted_at'])->findOrFail($bon);

        $lignes = $b->lignes->map(fn ($l) => [
            'id' => $l->id,
            'sequence' => $l->sequence,
            'command_id' => $l->command_id,
            'legacy_command_id' => $l->legacy_command_id,
            'code' => $l->command_code ?? $l->code,
            'destinataire' => $l->destinataire_nom ?? $l->nom_destinataire,
            'telephone' => $l->destinataire_telephone ?? $l->telephone,
            'ville' => $l->destinataire_ville ?? $l->ville,
            'montant' => (float) ($l->prix_brut ?? $l->prix ?? 0),
            'etat_actuel' => $l->command?->state,
            'groupe_actuel' => $l->command?->state_group,
            'colis_supprime' => $l->command?->deleted_at !== null,
            'anomalie' => $l->command_id === null ? ($l->anomalie_motif ?? 'Colis absent de l’ancienne base') : null,
        ]);

        $anomalies = RepriseAnomalie::query()->where('domaine', $domaine)->where('legacy_table', 'bls')
            ->where(fn ($q) => $q->where('legacy_id', (string) $b->source_bls_id)->orWhere('valeur_legacy', $b->code))
            ->orderBy('id')->get(['id', 'code', 'gravite', 'libelle', 'statut']);

        return Inertia::render('Bons/Show', [
            'type' => $type,
            'libelleType' => $type === 'retour' ? ($b->type === 'livreur' ? 'Bons de retour livreur' : 'Bons de retour client') : $libelle,
            'urlListe' => self::urlListe($type, $b->type ?? null),
            'bon' => $this->ligne($type, $b) + [
                'legacy_brut' => $b->legacy_brut,
                'traite_sans_pointage_historique' => (bool) ($b->traite_sans_pointage_historique ?? false),
                'deleted_at' => $b->deleted_at?->toDateTimeString(),
            ],
            'statuts' => $modele::STATUTS,
            'lignes' => $lignes,
            'anomalies' => $anomalies,
        ]);
    }

    /**
     * Actions groupées : mettre en corbeille (suppression logique) ou restaurer une sélection.
     * Les lignes du bon restent intactes ; pas de suppression définitive.
     */
    public function groupe(Request $request, string $type): RedirectResponse
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        [$modele] = self::TYPES[$type];
        Gate::authorize('update', $modele);

        $data = $request->validate([
            'action' => ['required', 'in:corbeille,restaurer'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $n = $data['action'] === 'corbeille'
            ? $modele::query()->whereIn('id', $data['ids'])->get()->each->delete()->count()
            : $modele::onlyTrashed()->whereIn('id', $data['ids'])->get()->each->restore()->count();

        return back()->with('success', "{$n} bon(s) ".($data['action'] === 'corbeille' ? 'mis en corbeille.' : 'restauré(s).'));
    }

    /** @return array<string, mixed> */
    private function ligne(string $type, Model $b): array
    {
        $titulaire = match ($type) {
            'livraison' => $b->livreur_nom,
            'envoi' => $b->hub ?? $b->livreur_nom,
            'ramassage' => $b->business_name ?? $b->nom_cliente,
            'retour' => $b->type === 'livreur' ? $b->livreur_nom : ($b->business_name ?? null),
        };

        return [
            'id' => $b->id,
            'code' => $b->code,
            'source_bls_id' => $b->source_bls_id,
            'status' => $b->status,
            'sous_type' => $type === 'retour' ? $b->type : null,
            'titulaire' => $titulaire,
            'total_colis' => (int) $b->total_colis,
            'montant' => (float) ($type === 'ramassage' ? $b->total : $b->montant_total_brut),
            'anomalies' => (int) ($b->anomalies_count ?? 0),
            'date' => $b->legacy_dateadd_at?->toDateTimeString() ?? $b->created_at?->toDateTimeString(),
            'supprime' => $b->deleted_at !== null,
        ];
    }

    /**
     * @param  class-string<Model>  $modele
     * @return array<string, mixed>
     */
    private function stats(string $modele, \Closure $restreindre): array
    {
        $parStatut = $modele::query()->tap($restreindre)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            'total' => (int) $parStatut->sum(),
            'par_statut' => $parStatut,
            'colis' => (int) $modele::query()->tap($restreindre)->sum('total_colis'),
            'corbeille' => $modele::onlyTrashed()->tap($restreindre)->count(),
        ];
    }
}
