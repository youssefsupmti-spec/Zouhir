<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeliveryPartner;
use App\Models\User;
use App\Models\ZoneCouverture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coverage Board : qui couvre chaque zone (titulaire + remplaçant de la flotte régionale,
 * ou partenaire du réseau national) et charge en cours de chaque zone.
 */
class CouvertureController extends Controller
{
    /** Groupes d'état comptés dans la charge d'une zone. */
    private const EN_COURS = ['en_cours', 'planifie'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DeliveryPartner::class);

        // Zones sans ligne de couverture (créées après la migration) : ligne vide « à affecter ».
        $sans = DB::table('zones')->whereNull('deleted_at')
            ->whereNotIn('id', DB::table('zone_couvertures')->select('zone_id'))->get(['id', 'type']);
        foreach ($sans as $z) {
            ZoneCouverture::create(['zone_id' => $z->id, 'type_couverture' => $z->type === 'locale' ? 'regional_flotte' : 'national_partenaire', 'statut_couverture' => 'non_couvert']);
        }

        $charges = DB::table('commands')->whereNull('deleted_at')->whereIn('state_group', self::EN_COURS)->whereNotNull('destinataire_zone_id')
            ->selectRaw('destinataire_zone_id AS zone_id, COUNT(*) AS n')->groupBy('destinataire_zone_id')->pluck('n', 'zone_id');

        $couvertures = ZoneCouverture::query()
            ->whereHas('zone')
            ->with(['zone' => fn ($q) => $q->withCount('villes'), 'titulaire:id,nom_complet,telephone,deleted_at', 'backup:id,nom_complet,telephone,deleted_at', 'partenaire:id,code,name,deleted_at'])
            ->get()
            ->sortBy(fn ($c) => [$c->type_couverture === 'regional_flotte' ? 0 : 1, $c->zone->code])
            ->values()
            ->map(function (ZoneCouverture $c) use ($charges) {
                $charge = (int) ($charges[$c->zone_id] ?? 0);

                return [
                    'id' => $c->id, 'type_couverture' => $c->type_couverture, 'statut_couverture' => $c->statut_couverture,
                    'zone' => ['id' => $c->zone->id, 'code' => $c->zone->code, 'name' => $c->zone->name, 'type' => $c->zone->type, 'villes' => $c->zone->villes_count],
                    'titulaire' => $c->titulaire ? ['id' => $c->titulaire->id, 'nom' => $c->titulaire->nom_complet, 'telephone' => $c->titulaire->telephone] : null,
                    'backup' => $c->backup ? ['id' => $c->backup->id, 'nom' => $c->backup->nom_complet, 'telephone' => $c->backup->telephone] : null,
                    'partenaire' => $c->partenaire ? ['id' => $c->partenaire->id, 'code' => $c->partenaire->code, 'name' => $c->partenaire->name] : null,
                    'capacite' => $c->capacite_journaliere,
                    'charge' => $charge,
                    'taux' => $c->capacite_journaliere ? (int) round($charge * 100 / $c->capacite_journaliere) : null,
                    'notes' => $c->notes,
                    'bascule_at' => $c->bascule_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('Zones/Couverture', [
            'couvertures' => $couvertures,
            'livreurs' => User::drivers()->where('active', true)->whereNotIn('id', DB::table('delivery_partners')->whereNotNull('user_id')->select('user_id'))
                ->orderBy('nom_complet')->get(['id', 'nom_complet', 'telephone']),
            'partenaires' => DeliveryPartner::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'statuts' => ZoneCouverture::STATUTS,
            'can' => ['manage' => $request->user()->can('update', DeliveryPartner::class)],
        ]);
    }

    public function update(Request $request, ZoneCouverture $couverture): RedirectResponse
    {
        Gate::authorize('update', DeliveryPartner::class);
        $data = $request->validate([
            'type_couverture' => ['required', Rule::in(array_keys(ZoneCouverture::TYPES))],
            'titulaire_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'backup_user_id' => ['nullable', 'integer', 'exists:users,id', 'different:titulaire_user_id'],
            'delivery_partner_id' => ['nullable', 'integer', 'exists:delivery_partners,id'],
            'capacite_journaliere' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['backup_user_id.different' => 'Le remplaçant doit être différent du titulaire.']);

        foreach (['titulaire_user_id', 'backup_user_id'] as $champ) {
            if (! empty($data[$champ]) && ! User::drivers()->whereKey($data[$champ])->exists()) {
                throw ValidationException::withMessages([$champ => 'Ce compte n’est pas un livreur.']);
            }
        }

        $regional = $data['type_couverture'] === 'regional_flotte';
        if ($regional) {
            $data['delivery_partner_id'] = null;
        } else {
            $data['titulaire_user_id'] = $data['backup_user_id'] = null;
        }
        $data['statut_couverture'] = $regional
            ? (! empty($data['titulaire_user_id']) ? (! empty($data['backup_user_id']) ? 'optimal' : 'normal') : 'non_couvert')
            : (! empty($data['delivery_partner_id']) ? 'normal' : 'non_couvert');
        $data['updated_by'] = $request->user()->id;
        $data['bascule_at'] = null;

        DB::transaction(function () use ($couverture, $data) {
            $couverture->update($data);
            // Garde les tables de rattachement Zakaria2 alignées (ajout seulement : rien n'est retiré).
            foreach (array_filter([$data['titulaire_user_id'] ?? null, $data['backup_user_id'] ?? null]) as $uid) {
                DB::table('livreur_zones')->insertOrIgnore(['user_id' => $uid, 'zone_id' => $couverture->zone_id, 'zone_type' => 'livraison', 'created_at' => now(), 'updated_at' => now()]);
            }
            if (! empty($data['delivery_partner_id'])) {
                DB::table('delivery_partner_zones')->insertOrIgnore(['delivery_partner_id' => $data['delivery_partner_id'], 'zone_id' => $couverture->zone_id, 'is_exclusif' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return back()->with('success', 'Couverture de la zone enregistrée.');
    }

    /**
     * Bascule sur le remplaçant : il devient titulaire et reprend les colis en cours de la zone
     * confiés à l'ancien titulaire. La zone passe en « Dégradé » jusqu'à la prochaine révision.
     */
    public function basculer(Request $request, ZoneCouverture $couverture): RedirectResponse
    {
        Gate::authorize('update', DeliveryPartner::class);
        if ($couverture->type_couverture !== 'regional_flotte' || ! $couverture->titulaire_user_id || ! $couverture->backup_user_id) {
            return back()->with('error', 'La bascule demande un titulaire et un remplaçant sur une zone de la flotte régionale.');
        }

        $backup = User::query()->findOrFail($couverture->backup_user_id);
        $n = DB::transaction(function () use ($couverture, $backup, $request) {
            $n = DB::table('commands')->whereNull('deleted_at')->where('destinataire_zone_id', $couverture->zone_id)
                ->where('livreur_id', $couverture->titulaire_user_id)->whereIn('state_group', self::EN_COURS)
                ->update(['livreur_id' => $backup->id, 'livreur_nom' => $backup->nom_complet, 'livreur_telephone' => $backup->telephone, 'updated_at' => now()]);

            $couverture->update([
                'titulaire_user_id' => $couverture->backup_user_id,
                'backup_user_id' => $couverture->titulaire_user_id,
                'statut_couverture' => 'degrade',
                'bascule_at' => now(),
                'updated_by' => $request->user()->id,
            ]);

            return $n;
        });

        return back()->with('success', "Bascule effectuée : {$backup->nom_complet} est titulaire, {$n} colis en cours réaffecté(s).");
    }
}
