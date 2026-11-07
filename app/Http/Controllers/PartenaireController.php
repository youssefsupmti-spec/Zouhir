<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Reseau\Api\PasserelleService;
use App\Models\DeliveryPartner;
use App\Models\PartnerStatusMapping;
use App\Models\PartnerWebhookLog;
use App\Models\Statut;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Réseau national : transporteurs partenaires, tarifs de transit par zone et passerelle API.
 */
class PartenaireController extends Controller
{
    /** Répertoire des transporteurs partenaires. */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DeliveryPartner::class);
        $corbeille = $request->boolean('corbeille');

        $colis = DB::table('commands')->whereNotNull('delivery_partner_id')->whereNull('deleted_at')
            ->selectRaw("delivery_partner_id, COUNT(*) AS n, SUM(CASE WHEN state_group = 'livre' THEN 1 ELSE 0 END) AS livres, COALESCE(SUM(CASE WHEN state_group = 'livre' THEN prix_brut ELSE 0 END), 0) AS crbt")
            ->groupBy('delivery_partner_id')->get()->keyBy('delivery_partner_id');

        $partenaires = DeliveryPartner::query()
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->with(['compte:id,nom_complet', 'ville:id,name_fr', 'zones:id,code,name'])
            ->withCount('bonsEnvoi')
            ->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (DeliveryPartner $p) => [
                'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'type_partenaire' => $p->type_partenaire,
                'ice' => $p->ice, 'telephone' => $p->telephone, 'email' => $p->email, 'adresse' => $p->adresse,
                'ville_id' => $p->ville_id, 'ville' => $p->ville?->name_fr, 'notes' => $p->notes, 'is_active' => $p->is_active,
                'commission_defaut' => $p->commission_defaut !== null ? (float) $p->commission_defaut : null,
                'delai_transit_moyen_h' => $p->delai_transit_moyen_h, 'compte' => $p->compte?->nom_complet,
                'zones' => $p->zones->map(fn ($z) => ['code' => $z->code, 'name' => $z->name])->values(),
                'api' => ['jeton' => $p->api_key_hash !== null, 'secret' => $p->webhook_secret !== null, 'derniere_synchro' => $p->last_sync_at?->toDateTimeString()],
                'bons' => $p->bons_envoi_count,
                'colis' => (int) ($colis[$p->id]->n ?? 0),
                'livres' => (int) ($colis[$p->id]->livres ?? 0),
                'crbt' => (float) ($colis[$p->id]->crbt ?? 0),
                'supprime' => $p->deleted_at !== null,
            ]);

        return Inertia::render('Partenaires/Index', [
            'partenaires' => $partenaires,
            'types' => DeliveryPartner::TYPES,
            'villes' => Inertia::defer(fn () => Ville::query()->orderBy('name_fr')->get(['id', 'name_fr'])),
            'compteurs' => [
                'actifs' => DeliveryPartner::query()->where('is_active', true)->count(),
                'inactifs' => DeliveryPartner::query()->where('is_active', false)->count(),
                'corbeille' => DeliveryPartner::onlyTrashed()->count(),
            ],
            'filters' => ['corbeille' => $corbeille],
            'can' => ['manage' => $request->user()->can('update', DeliveryPartner::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('update', DeliveryPartner::class);
        $data = $this->valider($request);
        $data['code'] = strtoupper(Str::slug($request->input('code') ?: $data['name'], ''));
        $request->merge(['code' => $data['code']])->validate(['code' => ['required', 'max:32', 'unique:delivery_partners,code']], ['code.unique' => 'Ce code partenaire existe déjà.']);

        $p = DeliveryPartner::create($data);

        return back()->with('success', "Partenaire « {$p->name} » créé.");
    }

    public function update(Request $request, DeliveryPartner $partenaire): RedirectResponse
    {
        Gate::authorize('update', $partenaire);
        $partenaire->update($this->valider($request));

        return back()->with('success', "Partenaire « {$partenaire->name} » enregistré.");
    }

    /** Corbeille (suppression logique) : les bons et colis gardent leur partenaire. */
    public function destroy(DeliveryPartner $partenaire): RedirectResponse
    {
        Gate::authorize('update', $partenaire);
        $partenaire->delete();

        return back()->with('success', "Partenaire « {$partenaire->name} » mis en corbeille.");
    }

    public function restaurer(int $partenaire): RedirectResponse
    {
        $p = DeliveryPartner::onlyTrashed()->findOrFail($partenaire);
        Gate::authorize('update', $p);
        $p->restore();

        return back()->with('success', "Partenaire « {$p->name} » restauré.");
    }

    /** Tarifs de transit : matrice zones nationales × partenaires actifs. */
    public function tarifs(Request $request): Response
    {
        Gate::authorize('viewAny', DeliveryPartner::class);

        $partenaires = DeliveryPartner::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'commission_defaut', 'delai_transit_moyen_h']);
        $cellules = DB::table('delivery_partner_zones')->whereIn('delivery_partner_id', $partenaires->pluck('id'))->get()
            ->mapWithKeys(fn ($l) => ["{$l->delivery_partner_id}-{$l->zone_id}" => [
                'tarif' => $l->tarif_transit_unitaire !== null ? (float) $l->tarif_transit_unitaire : null,
                'delai' => $l->delai_garanti_h, 'exclusif' => (bool) $l->is_exclusif,
            ]]);

        return Inertia::render('Partenaires/Tarifs', [
            'zones' => Zone::query()->where('type', 'nationale')->orderBy('code')->withCount('villes')->get(['id', 'code', 'name']),
            'partenaires' => $partenaires,
            'cellules' => $cellules,
            'can' => ['manage' => $request->user()->can('update', DeliveryPartner::class)],
        ]);
    }

    /** Enregistre la matrice : une cellule vide (ni tarif ni délai, non desservie) retire la zone du partenaire. */
    public function enregistrerTarifs(Request $request): RedirectResponse
    {
        Gate::authorize('update', DeliveryPartner::class);
        $data = $request->validate([
            'cellules' => ['required', 'array', 'max:2000'],
            'cellules.*.delivery_partner_id' => ['required', 'integer', 'exists:delivery_partners,id'],
            'cellules.*.zone_id' => ['required', 'integer', 'exists:zones,id'],
            'cellules.*.desservie' => ['required', 'boolean'],
            'cellules.*.tarif' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'cellules.*.delai' => ['nullable', 'integer', 'min:1', 'max:720'],
            'cellules.*.exclusif' => ['nullable', 'boolean'],
        ]);

        $n = DB::transaction(function () use ($data) {
            $n = 0;
            foreach ($data['cellules'] as $c) {
                $cle = ['delivery_partner_id' => $c['delivery_partner_id'], 'zone_id' => $c['zone_id']];
                if (! $c['desservie']) {
                    $n += DB::table('delivery_partner_zones')->where($cle)->delete();

                    continue;
                }
                DB::table('delivery_partner_zones')->updateOrInsert($cle, [
                    'tarif_transit_unitaire' => $c['tarif'] ?? null,
                    'delai_garanti_h' => $c['delai'] ?? null,
                    'is_exclusif' => (bool) ($c['exclusif'] ?? false),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
                $n++;
            }

            return $n;
        });

        return back()->with('success', "{$n} cellule(s) de la grille enregistrée(s).");
    }

    /** Passerelle API : clés, correspondances de statuts, journal des échanges. */
    public function api(Request $request): Response
    {
        Gate::authorize('viewAny', DeliveryPartner::class);
        $partenaireId = (int) $request->input('partenaire', 0);
        $partenaires = DeliveryPartner::query()->orderByDesc('is_active')->orderBy('name')->get();
        $courant = $partenaires->firstWhere('id', $partenaireId) ?? $partenaires->first();

        return Inertia::render('Partenaires/Api', [
            'partenaires' => $partenaires->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'is_active' => $p->is_active])->values(),
            'courant' => $courant ? [
                'id' => $courant->id, 'code' => $courant->code, 'name' => $courant->name, 'is_active' => $courant->is_active,
                'api_endpoint' => $courant->api_endpoint, 'format_payload' => $courant->format_payload,
                'jeton' => $courant->api_key_hash !== null, 'secret' => $courant->webhook_secret !== null,
                'derniere_synchro' => $courant->last_sync_at?->toDateTimeString(),
                'mappings' => $courant->statusMappings()->orderBy('partner_status')->get(['id', 'partner_status', 'colis_status', 'statut_id', 'notes']),
                'logs' => PartnerWebhookLog::query()->where('delivery_partner_id', $courant->id)->latest('id')->limit(50)
                    ->get(['id', 'direction', 'endpoint', 'http_status', 'processed', 'signature_valide', 'error_message', 'ip', 'created_at', 'command_id']),
                'stats' => PartnerWebhookLog::query()->where('delivery_partner_id', $courant->id)->where('created_at', '>=', now()->subDay())
                    ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN processed THEN 1 ELSE 0 END) AS ok')->first(),
            ] : null,
            'formats' => DeliveryPartner::FORMATS,
            'statutsStandard' => PasserelleService::STATUTS_STANDARD,
            'statuts' => Statut::query()->orderBy('libelle')->get(['id', 'libelle']),
            'baseUrl' => rtrim(url('/api/v1'), '/'),
            // Jeton ou secret tout juste généré : affiché UNE seule fois.
            'revele' => $request->session()->get('cle_revelee'),
            'can' => ['manage' => $request->user()->can('update', DeliveryPartner::class)],
        ]);
    }

    public function regenererCle(Request $request, DeliveryPartner $partenaire): RedirectResponse
    {
        Gate::authorize('update', $partenaire);
        $type = $request->validate(['type' => ['required', Rule::in(['jeton', 'secret'])]])['type'];
        $valeur = $type === 'jeton' ? $partenaire->genererJeton() : $partenaire->genererSecret();

        return redirect()->to('/partenaires/api?partenaire='.$partenaire->id)
            ->with('cle_revelee', ['type' => $type, 'valeur' => $valeur, 'partenaire' => $partenaire->id])
            ->with('success', $type === 'jeton' ? 'Nouveau jeton d’API généré : l’ancien ne fonctionne plus.' : 'Nouveau secret de signature généré.');
    }

    public function configurerApi(Request $request, DeliveryPartner $partenaire): RedirectResponse
    {
        Gate::authorize('update', $partenaire);
        $partenaire->update($request->validate([
            'api_endpoint' => ['nullable', 'url', 'max:255'],
            'format_payload' => ['required', Rule::in(array_keys(DeliveryPartner::FORMATS))],
        ]));

        return back()->with('success', 'Configuration API enregistrée.');
    }

    public function ajouterCorrespondance(Request $request, DeliveryPartner $partenaire): RedirectResponse
    {
        Gate::authorize('update', $partenaire);
        $data = $request->validate([
            'partner_status' => ['required', 'string', 'max:50', Rule::unique('partner_status_mappings')->where('delivery_partner_id', $partenaire->id)],
            'statut_id' => ['required', 'integer', 'exists:statuts,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['partner_status.unique' => 'Ce statut partenaire a déjà une correspondance.']);

        $partenaire->statusMappings()->create([
            'partner_status' => strtoupper(trim($data['partner_status'])),
            'statut_id' => $data['statut_id'],
            'colis_status' => (string) Statut::query()->whereKey($data['statut_id'])->value('libelle'),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Correspondance de statut ajoutée.');
    }

    public function supprimerCorrespondance(PartnerStatusMapping $correspondance): RedirectResponse
    {
        Gate::authorize('update', DeliveryPartner::class);
        $correspondance->delete();

        return back()->with('success', 'Correspondance retirée.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'type_partenaire' => ['required', Rule::in(array_keys(DeliveryPartner::TYPES))],
            'ice' => ['nullable', 'string', 'max:32'],
            'telephone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:191'],
            'adresse' => ['nullable', 'string', 'max:500'],
            'ville_id' => ['nullable', 'integer', 'exists:villes,id'],
            'commission_defaut' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'delai_transit_moyen_h' => ['nullable', 'integer', 'min:1', 'max:720'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
