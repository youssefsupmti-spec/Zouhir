<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use App\Models\Agence;
use Illuminate\Support\Facades\DB;

/**
 * Bons : `bls` legacy → quatre familles (règle des rapports 57 à 59, vérifiée sur la base) :
 *
 *   code `BL-…`, livreur (dlm) = compte partenaire  → bons_envoi     (+ bon_envoi_command)
 *   code `BL-…`, livreur (dlm) = autre compte       → bons_livraison (+ bon_livraison_command)
 *   code `BL-…`, sans livreur, avec client          → bon_ramassage  (+ bon_ramassage_lines)
 *   code `BR-…`                                     → bons_retour type client  (+ bon_retour_command)
 *   code `BRL-…`                                    → bons_retour type livreur (+ bon_retour_command)
 *   autre combinaison                               → NON reprise, signalée (bloquant)
 *
 * Lignes : le CSV `cmds` commence par un « 0 » technique (ignoré). Chaque id lu crée une ligne, même
 * si le colis n'existe plus (command_id NULL, legacy_command_id gardé, ligne marquée en anomalie).
 * Statut : l'ancien site n'a que « done » (on/off) — voir la décision « statut_des_bons ».
 * Aucun pointage n'existe dans l'ancien site : les bons « traités » le sont sans pointage historique.
 */
final class RepriseBons extends EtapeReprise
{
    private const FAMILLES = [
        'livraison' => ['table' => 'bons_livraison', 'lignes' => 'bon_livraison_command', 'cle' => 'bon_livraison_id', 'domaine' => 'bons_livraison'],
        'envoi' => ['table' => 'bons_envoi', 'lignes' => 'bon_envoi_command', 'cle' => 'bon_envoi_id', 'domaine' => 'bons_envoi'],
        'ramassage' => ['table' => 'bon_ramassage', 'lignes' => 'bon_ramassage_lines', 'cle' => 'bon_ramassage_id', 'domaine' => 'bons_ramassage'],
        'retour' => ['table' => 'bons_retour', 'lignes' => 'bon_retour_command', 'cle' => 'bon_retour_id', 'domaine' => 'bons_retour'],
    ];

    public static function nom(): string
    {
        return 'bons';
    }

    public static function domaines(): array
    {
        return ['bons_livraison', 'bons_envoi', 'bons_ramassage', 'bons_retour'];
    }

    public function executer(): array
    {
        $agenceId = Agence::principale()?->id;
        $partenaires = DB::table('delivery_partners')->whereNotNull('user_id')->pluck('id', 'user_id');
        $comptesPartenaires = array_map('intval', config('reprise.comptes_partenaires', []));
        $users = DB::table('users')->get(['id', 'nom_complet', 'telephone'])->keyBy('id');
        $business = [];
        foreach (DB::table('businesses')->orderBy('id')->get(['id', 'user_id', 'name']) as $b) {
            $business[(int) $b->user_id] ??= $b;
        }

        $chiffres = array_fill_keys(array_keys(self::FAMILLES), 0) + ['non_classes' => 0, 'lignes' => 0, 'colis_absents' => 0];
        $parCode = [];
        $envoiParColis = [];

        $this->legacy->table('bls')->orderBy('id')->chunkById(300, function ($lot) use (
            $agenceId, $partenaires, $comptesPartenaires, $users, $business, &$chiffres, &$parCode, &$envoiParColis
        ) {
            // Colis cités par ce lot de bons, lus en une fois.
            $tous = [];
            $lus = [];
            foreach ($lot as $b) {
                $lus[$b->id] = $this->lireCsv($b);
                foreach ($lus[$b->id]['ids'] as $cid) {
                    $tous[$cid] = true;
                }
            }
            $colis = DB::table('commands')->whereIn('id', array_keys($tous))
                ->get(['id', 'code', 'destinataire_nom', 'destinataire_telephone', 'destinataire_ville', 'destinataire_ville_id',
                    'destinataire_zone_code', 'prix_brut', 'frais_livraison_client', 'montant_supplementaire', 'montant_emballage', 'produit', 'state'])
                ->keyBy('id');

            foreach ($lot as $b) {
                $code = trim((string) $b->code);
                $prefixe = strtoupper((string) strstr($code, '-', true));
                $dlm = (int) $b->dlm;
                $client = (int) $b->client;
                $famille = match (true) {
                    $prefixe === 'BL' && $dlm > 0 && in_array($dlm, $comptesPartenaires, true) => 'envoi',
                    $prefixe === 'BL' && $dlm > 0 && $client === 0 => 'livraison',
                    $prefixe === 'BL' && $dlm === 0 && $client > 0 => 'ramassage',
                    $prefixe === 'BR' && $client > 0 => 'retour',
                    $prefixe === 'BRL' && $dlm > 0 => 'retour',
                    default => null,
                };

                if ($famille === null) {
                    $chiffres['non_classes']++;
                    $this->journal->signaler('bons_livraison', 'bon_non_classe', 'bloquant',
                        "Bon {$code} (#{$b->id}) non repris : combinaison inconnue (préfixe « {$prefixe} », livreur #{$dlm}, client #{$client})",
                        'bls', $b->id, $code, (array) $b);

                    continue;
                }
                $f = self::FAMILLES[$famille];
                $chiffres[$famille]++;
                $parCode[$code][] = ['id' => (int) $b->id, 'domaine' => $f['domaine']];

                foreach ($lus[$b->id]['invalides'] as $jeton) {
                    $this->journal->signaler($f['domaine'], 'jeton_invalide', 'attention', "Bon {$code} : élément « {$jeton} » de la liste de colis n’est pas un id", 'bls', $b->id, $jeton);
                }
                foreach ($lus[$b->id]['doublons'] as $cid) {
                    $this->journal->signaler($f['domaine'], 'colis_en_double_dans_bon', 'info', "Bon {$code} : le colis #{$cid} est listé plusieurs fois (une seule ligne reprise)", 'bls', $b->id, (string) $cid);
                }

                $livreur = $dlm > 0 ? ($users[$dlm] ?? null) : null;
                if ($dlm > 0 && $livreur === null) {
                    $this->journal->signaler($f['domaine'], 'livreur_du_bon_introuvable', 'bloquant', "Bon {$code} : livreur #{$dlm} introuvable", 'bls', $b->id, (string) $dlm);
                }
                $clientUser = $client > 0 ? ($users[$client] ?? null) : null;
                if ($client > 0 && $clientUser === null) {
                    $this->journal->signaler($f['domaine'], 'client_du_bon_introuvable', 'bloquant', "Bon {$code} : client #{$client} introuvable", 'bls', $b->id, (string) $client);
                }
                $biz = $clientUser !== null ? ($business[(int) $clientUser->id] ?? null) : null;

                $termine = trim((string) $b->done) === 'on';
                $date = self::date($b->dateadd);
                $commun = [
                    'code' => $code,
                    'source_bls_id' => (int) $b->id,
                    'agence_id' => $agenceId,
                    'legacy_dateadd_at' => $date,
                    'legacy_brut' => json_encode((array) $b, JSON_UNESCAPED_UNICODE),
                    'created_at' => $date,
                    'updated_at' => $date,
                    'deleted_at' => (int) $b->trash === 0 ? $date : null,
                ];

                $entete = match ($famille) {
                    'livraison', 'envoi' => $commun + [
                        'livreur_id' => $livreur?->id,
                        'livreur_nom' => $livreur?->nom_complet,
                        'livreur_telephone' => $livreur?->telephone,
                        'status' => $termine ? 'traite' : 'expedie',
                        'expedie_at' => $date,
                        'traite_sans_pointage_historique' => $termine,
                    ] + ($famille === 'envoi' ? ['delivery_partner_id' => $partenaires[$dlm] ?? null, 'hub' => $livreur?->nom_complet] : []),
                    'ramassage' => $commun + [
                        'client_id' => $clientUser?->id,
                        'business_id' => $biz?->id,
                        'business_name' => $biz?->name,
                        'nom_cliente' => $clientUser?->nom_complet,
                        'status' => $termine ? 'traite' : 'valide',
                    ],
                    'retour' => $commun + [
                        'type' => $prefixe === 'BRL' ? 'livreur' : 'client',
                        'client_id' => $clientUser?->id,
                        'business_id' => $biz?->id,
                        'business_name' => $biz?->name,
                        'livreur_id' => $livreur?->id,
                        'livreur_nom' => $livreur?->nom_complet,
                        'status' => $termine ? 'traite' : 'enregistre',
                    ],
                };

                // Lignes
                $lignes = [];
                $montant = 0.0;
                $frais = 0.0;
                $absents = 0;
                foreach ($lus[$b->id]['ids'] as $seq => $cid) {
                    $c = $colis[$cid] ?? null;
                    if ($c === null) {
                        $absents++;
                        $this->journal->signaler($f['domaine'], 'colis_du_bon_introuvable', 'attention', "Bon {$code} : le colis #{$cid} n’existe plus dans l’ancienne base (ligne gardée, sans colis)", 'bls', $b->id, (string) $cid, [], $f['table']);
                    } else {
                        $montant += (float) $c->prix_brut;
                        $frais += (float) $c->frais_livraison_client;
                        if ($famille === 'envoi') {
                            $envoiParColis[(int) $c->id] = $partenaires[$dlm] ?? null;
                        }
                    }
                    $lignes[] = $this->ligne($famille, $seq + 1, $cid, $c);
                }
                $chiffres['lignes'] += count($lignes);
                $chiffres['colis_absents'] += $absents;

                $entete['total_colis'] = count($lignes);
                if ($famille === 'ramassage') {
                    $entete['total'] = round($montant, 2);
                } else {
                    $entete['montant_total_brut'] = round($montant, 2);
                }
                if (in_array($famille, ['livraison', 'envoi'], true)) {
                    $entete['frais_total'] = round($frais, 2);
                    $entete['anomalies_count'] = $absents;
                }

                if (! $this->simulation) {
                    DB::transaction(function () use ($f, $entete, $lignes) {
                        DB::table($f['table'])->upsert([$entete], ['source_bls_id'], array_keys($entete));
                        $bonId = (int) DB::table($f['table'])->where('source_bls_id', $entete['source_bls_id'])->value('id');
                        DB::table($f['lignes'])->where($f['cle'], $bonId)->delete();
                        foreach (array_chunk($lignes, 500) as $morceau) {
                            DB::table($f['lignes'])->insert(array_map(fn ($l) => $l + [$f['cle'] => $bonId], $morceau));
                        }
                    });
                }
            }
            $this->info('  bons : '.array_sum(array_intersect_key($chiffres, self::FAMILLES)));
        });

        foreach ($parCode as $code => $bons) {
            if (count($bons) > 1) {
                $this->journal->signaler($bons[0]['domaine'], 'code_bon_en_double', 'attention', "Le code de bon « {$code} » est porté par ".count($bons).' bons (#'.implode(', #', array_column($bons, 'id')).')', 'bls', null, $code, ['bls_ids' => array_column($bons, 'id')]);
            }
        }

        // Colis remis au réseau national : partenaire du dernier bon d'envoi qui les contient.
        if (! $this->simulation) {
            foreach (array_chunk($envoiParColis, 500, true) as $morceau) {
                foreach ($morceau as $cid => $pid) {
                    DB::table('commands')->where('id', $cid)->update(['delivery_partner_id' => $pid]);
                }
            }
        }

        $this->journal->signaler('bons_livraison', 'statut_des_bons', 'decision',
            'Statut des bons repris : l’ancien site ne connaît que « terminé » (on/off). Terminé → « traité » ; sinon BL/BE « expédié », ramassage « validé », retour « enregistré ». Aucun bon n’a de pointage historique.',
            'bls', null, 'regle');

        $this->journal->cloturer(self::domaines());

        return $chiffres;
    }

    /** @return array{ids: list<int>, invalides: list<string>, doublons: list<int>} */
    private function lireCsv(object $b): array
    {
        $ids = [];
        $invalides = [];
        $doublons = [];
        foreach (explode(',', (string) $b->cmds) as $jeton) {
            $jeton = trim($jeton);
            if ($jeton === '' || $jeton === '0') {
                continue; // séparateurs vides et « 0 » d'initialisation de l'ancien code
            }
            if (! ctype_digit($jeton)) {
                $invalides[] = $jeton;

                continue;
            }
            $id = (int) $jeton;
            if (in_array($id, $ids, true)) {
                $doublons[] = $id;

                continue;
            }
            $ids[] = $id;
        }

        return ['ids' => $ids, 'invalides' => $invalides, 'doublons' => array_values(array_unique($doublons))];
    }

    /** @return array<string, mixed> */
    private function ligne(string $famille, int $sequence, int $legacyId, ?object $c): array
    {
        $now = now();
        if ($famille === 'ramassage') {
            return [
                'command_id' => $c?->id,
                'legacy_command_id' => $legacyId,
                'sequence' => $sequence,
                'code' => $c?->code,
                'nom_destinataire' => $c?->destinataire_nom,
                'telephone' => $c?->destinataire_telephone,
                'ville' => $c?->destinataire_ville,
                'produit' => $c?->produit,
                'prix' => $c?->prix_brut,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $base = [
            'command_id' => $c?->id,
            'legacy_command_id' => $legacyId,
            'sequence' => $sequence,
            'command_code' => $c?->code,
            'destinataire_nom' => $c?->destinataire_nom,
            'destinataire_telephone' => $c?->destinataire_telephone,
            'destinataire_ville' => $c?->destinataire_ville,
            'destinataire_zone_code' => $c?->destinataire_zone_code,
            'prix_brut' => $c?->prix_brut,
            'total' => $c?->prix_brut,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($famille === 'retour') {
            return $base + ['etat_au_bon' => null]; // état au moment du bon : inconnu dans l'ancien site
        }

        return $base + [
            'destinataire_ville_id' => $c?->destinataire_ville_id,
            'frais_livraison_client' => $c?->frais_livraison_client ?? 0,
            'montant_supplementaire' => $c?->montant_supplementaire ?? 0,
            'montant_emballage' => $c?->montant_emballage ?? 0,
            'ajout_methode' => 'reprise',
            'is_anomalie' => $c === null,
            'anomalie_motif' => $c === null ? 'Colis absent de l’ancienne base' : null,
        ];
    }
}
