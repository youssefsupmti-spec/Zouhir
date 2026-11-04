<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepriseAnomalie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Reprise des données » : tout ce que la reprise de l'ancienne base n'a pas rattaché exactement,
 * par domaine (onglets), avec la décision prise pour chaque ligne.
 */
class RepriseAnomalieController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RepriseAnomalie::class);

        $domaines = array_keys(RepriseAnomalie::DOMAINES);
        $domaine = in_array($request->input('domaine'), $domaines, true) ? (string) $request->input('domaine') : 'tous';
        $statut = array_key_exists((string) $request->input('statut'), RepriseAnomalie::STATUTS) ? (string) $request->input('statut') : 'ouverte';
        $gravite = array_key_exists((string) $request->input('gravite'), RepriseAnomalie::GRAVITES) ? (string) $request->input('gravite') : '';
        $code = trim((string) $request->input('code', ''));
        $search = trim((string) $request->input('search', ''));
        $parPage = min(max((int) $request->input('per_page', 25), 10), 100);

        $base = fn () => RepriseAnomalie::query()
            ->when($domaine !== 'tous', fn ($q) => $q->where('domaine', $domaine))
            ->where('statut', $statut);

        $anomalies = $base()
            ->when($gravite !== '', fn ($q) => $q->where('gravite', $gravite))
            ->when($code !== '', fn ($q) => $q->where('code', $code))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('libelle', 'like', "%{$search}%")
                ->orWhere('valeur_legacy', 'like', "%{$search}%")->orWhere('legacy_id', $search)))
            ->with('traitePar:id,nom_complet')
            ->orderByRaw("CASE gravite WHEN 'bloquant' THEN 0 WHEN 'decision' THEN 1 WHEN 'attention' THEN 2 ELSE 3 END")
            ->orderBy('domaine')->orderBy('code')->orderBy('id')
            ->paginate($parPage)->withQueryString()
            ->through(fn (RepriseAnomalie $a) => [
                'id' => $a->id,
                'domaine' => $a->domaine,
                'code' => $a->code,
                'gravite' => $a->gravite,
                'libelle' => $a->libelle,
                'legacy_table' => $a->legacy_table,
                'legacy_id' => $a->legacy_id,
                'valeur_legacy' => $a->valeur_legacy,
                'cible_table' => $a->cible_table,
                'cible_id' => $a->cible_id,
                'nb_elements' => self::nbElements($a->details),
                'details' => self::detailsCourts($a->details),
                'statut' => $a->statut,
                'decision' => $a->decision,
                'traite_par' => $a->traitePar?->nom_complet,
                'traite_le' => $a->traite_le?->toDateTimeString(),
                'vue_le' => $a->vue_le?->toDateTimeString(),
            ]);

        $parDomaine = DB::table('reprise_anomalies')->where('statut', $statut)->selectRaw('domaine, COUNT(*) AS n')->groupBy('domaine')->pluck('n', 'domaine');

        return Inertia::render('Reprise/Index', [
            'anomalies' => $anomalies,
            'domaines' => RepriseAnomalie::DOMAINES,
            'gravites' => RepriseAnomalie::GRAVITES,
            'statuts' => RepriseAnomalie::STATUTS,
            'compteurs' => ['tous' => (int) $parDomaine->sum()] + $parDomaine->map(fn ($n) => (int) $n)->all(),
            'codes' => $base()->selectRaw('code, COUNT(*) AS n')->groupBy('code')->orderBy('code')->pluck('n', 'code'),
            'stats' => [
                'ouvertes' => RepriseAnomalie::where('statut', 'ouverte')->count(),
                'bloquantes' => RepriseAnomalie::where('statut', 'ouverte')->where('gravite', 'bloquant')->count(),
                'decisions' => RepriseAnomalie::where('statut', 'ouverte')->where('gravite', 'decision')->count(),
                'traitees' => RepriseAnomalie::where('statut', '<>', 'ouverte')->count(),
                'dernier_passage' => RepriseAnomalie::max('vue_le'),
            ],
            'filters' => compact('domaine', 'statut', 'gravite', 'code', 'search') + ['per_page' => $parPage],
            'can' => ['manage' => $request->user()->can('update', RepriseAnomalie::class)],
        ]);
    }

    public function update(Request $request, RepriseAnomalie $anomalie): RedirectResponse
    {
        Gate::authorize('update', $anomalie);

        $data = $request->validate([
            'statut' => ['required', Rule::in(array_keys(RepriseAnomalie::STATUTS))],
            'decision' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('statut') !== 'ouverte')],
        ], ['decision.required' => 'Indiquez la décision prise (elle reste attachée à l’anomalie).']);

        $anomalie->update($data['statut'] === 'ouverte'
            ? ['statut' => 'ouverte', 'decision' => $data['decision'] ?? $anomalie->decision, 'traite_par' => null, 'traite_le' => null]
            : $data + ['traite_par' => $request->user()->id, 'traite_le' => now()]);

        return back()->with('success', 'Anomalie '.mb_strtolower(RepriseAnomalie::STATUTS[$data['statut']]).'.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', RepriseAnomalie::class);
        $domaine = (string) $request->input('domaine', 'tous');

        return response()->streamDownload(function () use ($domaine) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'domaine', 'code', 'gravite', 'statut', 'libelle', 'legacy_table', 'legacy_id', 'valeur_legacy', 'cible_table', 'cible_id', 'decision'], ';');
            DB::table('reprise_anomalies')->when($domaine !== 'tous', fn ($q) => $q->where('domaine', $domaine))
                ->orderBy('domaine')->orderBy('code')->orderBy('id')
                ->chunk(1000, function ($lot) use ($out) {
                    foreach ($lot as $a) {
                        fputcsv($out, [$a->id, $a->domaine, $a->code, $a->gravite, $a->statut, $a->libelle, $a->legacy_table, $a->legacy_id, $a->valeur_legacy, $a->cible_table, $a->cible_id, $a->decision], ';');
                    }
                });
            fclose($out);
        }, 'reprise-anomalies-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<string, mixed>|null $details */
    private static function nbElements(?array $details): ?int
    {
        foreach (['colis', 'lignes'] as $cle) {
            if (isset($details[$cle]) && is_int($details[$cle])) {
                return $details[$cle];
            }
        }

        return null;
    }

    /**
     * Détails affichables : listes d'ids tronquées (certaines anomalies agrégées en portent des centaines).
     *
     * @param  array<string, mixed>|null  $details
     * @return array<string, mixed>
     */
    private static function detailsCourts(?array $details): array
    {
        $court = [];
        foreach ($details ?? [] as $cle => $valeur) {
            if (is_array($valeur) && array_is_list($valeur) && count($valeur) > 30) {
                $court[$cle] = array_slice($valeur, 0, 30);
                $court[$cle.'_total'] = count($valeur);
            } else {
                $court[$cle] = $valeur;
            }
        }

        return $court;
    }
}
