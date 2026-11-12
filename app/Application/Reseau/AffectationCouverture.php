<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use App\Models\User;
use App\Models\ZoneCouverture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Propose la couverture de chaque zone à partir de l'historique réel des colis (rien n'est deviné) :
 *
 * - zone régionale (type « locale ») : titulaire = livreur actif qui a livré le plus de colis dans la zone sur la
 *   fenêtre, remplaçant = le deuxième ; chacun doit avoir au moins SEUIL livraisons. Capacité journalière =
 *   90e centile du nombre de colis livrés par jour dans la zone (jours avec au moins une livraison).
 * - zone nationale : partenaire actif qui a reçu le plus de colis de la zone sur la fenêtre (au moins SEUIL).
 * - zone de repli UKN : jamais affectée (décision du propriétaire).
 *
 * La fenêtre se termine à la dernière livraison connue (et non à « aujourd'hui ») pour que le résultat soit
 * reproductible. Seules les couvertures encore vides (« non couvert », sans titulaire, partenaire ni capacité)
 * sont remplies : une saisie manuelle n'est jamais écrasée.
 */
class AffectationCouverture
{
    public const FENETRE_JOURS = 90;

    public const SEUIL = 10;

    public const ZONES_EXCLUES = ['UKN'];

    /** @return array{fin: ?string, debut: ?string, lignes: list<array<string, mixed>>} */
    public function analyser(): array
    {
        $fin = DB::table('commands')->where('state_group', 'livre')->max('livre_at');
        if (! $fin) {
            return ['fin' => null, 'debut' => null, 'lignes' => []];
        }
        $fin = Carbon::parse($fin);
        $debut = $fin->copy()->subDays(self::FENETRE_JOURS);

        $comptesPartenaires = DB::table('delivery_partners')->whereNotNull('user_id')->pluck('user_id')->all();
        $livreurs = User::drivers()->where('active', true)->whereNotIn('id', $comptesPartenaires)->pluck('nom_complet', 'id');
        $partenaires = DB::table('delivery_partners')->whereNull('deleted_at')->where('is_active', true)->pluck('name', 'id');

        // Les couvertures manquantes sont créées vides, comme le fait le Coverage Board.
        $sans = DB::table('zones')->whereNull('deleted_at')->whereNotIn('id', DB::table('zone_couvertures')->select('zone_id'))->get(['id', 'type']);
        foreach ($sans as $z) {
            ZoneCouverture::create(['zone_id' => $z->id, 'type_couverture' => $z->type === 'locale' ? 'regional_flotte' : 'national_partenaire', 'statut_couverture' => 'non_couvert']);
        }

        $lignes = [];
        $couvertures = ZoneCouverture::query()->with('zone')->whereHas('zone')->get()->sortBy(fn ($c) => [($c->zone->type instanceof \BackedEnum ? $c->zone->type->value : $c->zone->type) === 'locale' ? 0 : 1, $c->zone->code]);
        foreach ($couvertures as $c) {
            $z = $c->zone;
            $type = $z->type instanceof \BackedEnum ? $z->type->value : (string) $z->type;
            $l = ['couverture_id' => $c->id, 'zone_id' => $z->id, 'code' => $z->code, 'nom' => $z->name, 'type' => $type,
                'action' => 'ignorer', 'raison' => '', 'titulaire' => null, 'backup' => null, 'partenaire' => null, 'capacite' => null, 'notes' => null];

            $vide = $c->statut_couverture === 'non_couvert' && ! $c->titulaire_user_id && ! $c->backup_user_id && ! $c->delivery_partner_id && ! $c->capacite_journaliere;
            if (in_array($z->code, self::ZONES_EXCLUES, true)) {
                $l['raison'] = 'zone de repli : décision du propriétaire';
            } elseif (! $vide) {
                $l['raison'] = 'déjà renseignée : conservée';
            } elseif ($type === 'locale') {
                $this->regionale($l, $debut, $fin, $livreurs->all());
            } else {
                $this->nationale($l, $debut, $fin, $partenaires->all());
            }
            $lignes[] = $l;
        }

        return ['fin' => $fin->toDateTimeString(), 'debut' => $debut->toDateTimeString(), 'lignes' => $lignes];
    }

    /** @param array<int, string> $livreurs */
    private function regionale(array &$l, Carbon $debut, Carbon $fin, array $livreurs): void
    {
        $livres = DB::table('commands')->whereNull('deleted_at')->where('destinataire_zone_id', $l['zone_id'])
            ->where('state_group', 'livre')->whereBetween('livre_at', [$debut, $fin]);

        $classement = (clone $livres)->whereNotNull('livreur_id')->whereIn('livreur_id', array_keys($livreurs))
            ->selectRaw('livreur_id, COUNT(*) AS n')->groupBy('livreur_id')->orderByDesc('n')->orderBy('livreur_id')->get()
            ->filter(fn ($r) => (int) $r->n >= self::SEUIL)->values();

        if ($classement->isEmpty()) {
            $l['raison'] = 'aucun livreur actif avec au moins '.self::SEUIL.' livraisons sur '.self::FENETRE_JOURS.' jours';

            return;
        }

        $parJour = (clone $livres)->selectRaw('DATE(livre_at) AS j, COUNT(*) AS n')->groupBy('j')->pluck('n')->map(fn ($n) => (int) $n)->sort()->values()->all();
        $l['capacite'] = max(1, $parJour[(int) max(0, ceil(0.9 * count($parJour)) - 1)]);

        $t = $classement[0];
        $l['titulaire'] = ['id' => (int) $t->livreur_id, 'nom' => $livreurs[$t->livreur_id], 'livres' => (int) $t->n];
        if (isset($classement[1])) {
            $b = $classement[1];
            $l['backup'] = ['id' => (int) $b->livreur_id, 'nom' => $livreurs[$b->livreur_id], 'livres' => (int) $b->n];
        }
        $l['action'] = 'affecter';
        $l['notes'] = mb_substr(sprintf('Affectation auto. (%s) sur %d j d\'historique : titulaire %d livrés%s ; capacité = 90e centile des livraisons/jour.',
            now()->format('d/m/Y'), self::FENETRE_JOURS, $l['titulaire']['livres'], $l['backup'] ? ', remplaçant '.$l['backup']['livres'] : ', pas de 2e livreur au seuil'), 0, 255);
    }

    /** @param array<int, string> $partenaires */
    private function nationale(array &$l, Carbon $debut, Carbon $fin, array $partenaires): void
    {
        $p = DB::table('commands')->whereNull('deleted_at')->where('destinataire_zone_id', $l['zone_id'])
            ->whereIn('delivery_partner_id', array_keys($partenaires))->whereBetween('created_at', [$debut, $fin])
            ->selectRaw('delivery_partner_id, COUNT(*) AS n')->groupBy('delivery_partner_id')->orderByDesc('n')->orderBy('delivery_partner_id')->first();

        if (! $p || (int) $p->n < self::SEUIL) {
            $l['raison'] = 'aucun partenaire actif avec au moins '.self::SEUIL.' colis sur '.self::FENETRE_JOURS.' jours';

            return;
        }

        $l['partenaire'] = ['id' => (int) $p->delivery_partner_id, 'nom' => $partenaires[$p->delivery_partner_id], 'colis' => (int) $p->n];
        $l['action'] = 'affecter';
        $l['notes'] = mb_substr(sprintf('Affectation auto. (%s) : partenaire principal de la zone sur %d j (%d colis).', now()->format('d/m/Y'), self::FENETRE_JOURS, $p->n), 0, 255);
    }

    /** @return array{affectees: int, rattachements_livreurs: int, rattachements_partenaires: int} */
    public function appliquer(array $analyse): array
    {
        $r = ['affectees' => 0, 'rattachements_livreurs' => 0, 'rattachements_partenaires' => 0];

        DB::transaction(function () use ($analyse, &$r) {
            foreach ($analyse['lignes'] as $l) {
                if ($l['action'] !== 'affecter') {
                    continue;
                }
                // Revérifie au moment d'écrire : une saisie faite entre-temps n'est pas écrasée.
                $n = ZoneCouverture::query()->whereKey($l['couverture_id'])->where('statut_couverture', 'non_couvert')
                    ->whereNull('titulaire_user_id')->whereNull('backup_user_id')->whereNull('delivery_partner_id')->whereNull('capacite_journaliere')
                    ->update([
                        'titulaire_user_id' => $l['titulaire']['id'] ?? null,
                        'backup_user_id' => $l['backup']['id'] ?? null,
                        'delivery_partner_id' => $l['partenaire']['id'] ?? null,
                        'capacite_journaliere' => $l['capacite'],
                        'statut_couverture' => $l['partenaire'] ? 'normal' : ($l['backup'] ? 'optimal' : 'normal'),
                        'notes' => $l['notes'],
                        'updated_at' => now(),
                    ]);
                if (! $n) {
                    continue;
                }
                $r['affectees']++;
                foreach (array_filter([$l['titulaire']['id'] ?? null, $l['backup']['id'] ?? null]) as $uid) {
                    $r['rattachements_livreurs'] += DB::table('livreur_zones')->insertOrIgnore(['user_id' => $uid, 'zone_id' => $l['zone_id'], 'zone_type' => 'livraison', 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($l['partenaire']) {
                    $r['rattachements_partenaires'] += DB::table('delivery_partner_zones')->insertOrIgnore(['delivery_partner_id' => $l['partenaire']['id'], 'zone_id' => $l['zone_id'], 'is_exclusif' => false, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });

        return $r;
    }
}
