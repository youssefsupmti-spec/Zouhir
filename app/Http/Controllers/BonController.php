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

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BonLivraison::class);

        $type = array_key_exists((string) $request->input('type'), self::TYPES) ? (string) $request->input('type') : 'livraison';
        [$modele] = self::TYPES[$type];
        $statut = (string) $request->input('statut', '');
        $search = trim((string) $request->input('search', ''));
        $parPage = min(max((int) $request->input('per_page', 25), 10), 100);
        $corbeille = $request->boolean('corbeille');

        /** @var Builder $q */
        $q = $modele::query()
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
            }))
            ->when($type === 'retour' && in_array($request->input('sous_type'), ['client', 'livreur'], true), fn ($w) => $w->where('type', $request->input('sous_type')));

        $bons = $q->orderByDesc('legacy_dateadd_at')->orderByDesc('id')->paginate($parPage)->withQueryString()
            ->through(fn (Model $b) => $this->ligne($type, $b));

        return Inertia::render('Bons/Index', [
            'type' => $type,
            'bons' => $bons,
            'compteurs' => collect(self::TYPES)->map(fn ($t) => $t[0]::query()->count()),
            'stats' => $this->stats($modele),
            'statuts' => $modele::STATUTS,
            'filters' => ['statut' => $statut, 'search' => $search, 'per_page' => $parPage, 'corbeille' => $corbeille, 'sous_type' => (string) $request->input('sous_type', '')],
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
            'libelleType' => $libelle,
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
    private function stats(string $modele): array
    {
        $parStatut = $modele::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            'total' => (int) $parStatut->sum(),
            'par_statut' => $parStatut,
            'colis' => (int) $modele::query()->sum('total_colis'),
            'corbeille' => $modele::onlyTrashed()->count(),
        ];
    }
}
