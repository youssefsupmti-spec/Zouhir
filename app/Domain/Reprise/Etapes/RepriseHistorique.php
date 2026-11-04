<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use App\Domain\Reprise\CarteStatuts;
use Illuminate\Support\Facades\DB;

/**
 * Historique : `commandshistory` → `command_status_histories` (+ jalons datés des colis).
 *
 * L'ancienne table relie l'événement au colis par son CODE (`command`), pas par son id. Un code
 * porté par un seul colis est rattaché ; un code inconnu ou en double garde la ligne (command_id
 * NULL, code conservé) et est signalé. `from_state` = état de la ligne précédente du même code.
 * L'auteur (`agent`, un nom) est rattaché seulement s'il correspond à UN compte de l'ancien site.
 *
 * Jalons (seulement depuis un événement réel, jamais déduits) :
 *   livre_at              = dernier « Livré », et seulement si le colis est livré aujourd'hui
 *   ramasse_at            = premier « Ramassé »
 *   expedie_at            = première « Mise en distribution » / « Prêt pour expédition »
 *   retour_recu_client_at = dernier « Retour client reçu »
 *   retour_agence_at      = premier retour reçu en agence / hub
 */
final class RepriseHistorique extends EtapeReprise
{
    private const JALONS = ['livre_at', 'ramasse_at', 'expedie_at', 'retour_recu_client_at', 'retour_agence_at'];

    public static function nom(): string
    {
        return 'historique';
    }

    public static function domaines(): array
    {
        return ['historique'];
    }

    public function executer(): array
    {
        // code → ids des colis (corbeille comprise)
        $parCode = [];
        foreach (DB::table('commands')->select(['id', 'code'])->orderBy('id')->cursor() as $c) {
            $parCode[(string) $c->code][] = (int) $c->id;
        }

        // nom legacy → comptes (rattachement seulement si UN compte porte ce nom)
        $parNom = [];
        foreach ($this->legacy->table('users')->get(['id', 'fullname', 'type']) as $u) {
            $parNom[trim((string) $u->fullname)][] = ['id' => (int) $u->id, 'type' => (string) $u->type];
        }
        $existants = DB::table('users')->pluck('id', 'id');
        $statuts = RepriseStatuts::carte();

        $jalons = [];
        $sansColis = [];
        $ambigus = [];
        $auteursInconnus = [];
        $auteursAmbigus = [];
        $codePrecedent = null;
        $etatPrecedent = null;
        $lot = [];
        $total = 0;
        $rattaches = 0;

        // 415 000 lignes triées par code : lecture en flux (requête non mise en mémoire tampon sous MySQL).
        $pdo = $this->legacy->getPdo();
        $mysql = $this->legacy->getDriverName() === 'mysql';
        if ($mysql) {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }

        $curseur = $this->legacy->table('commandshistory')
            ->select(['id', 'command', 'state', 'agent', 'dateadd'])
            ->orderBy('command')->orderByRaw('dateadd + 0')->orderBy('id')
            ->cursor();

        foreach ($curseur as $h) {
            $total++;
            $code = (string) $h->command;
            $etat = trim((string) $h->state);
            if ($code !== $codePrecedent) {
                $etatPrecedent = null;
                $codePrecedent = $code;
            }

            $ids = $parCode[$code] ?? [];
            $commandId = count($ids) === 1 ? $ids[0] : null;
            if ($ids === []) {
                $sansColis[$code] = ($sansColis[$code] ?? 0) + 1;
            } elseif (count($ids) > 1) {
                $ambigus[$code] = ($ambigus[$code] ?? 0) + 1;
            } else {
                $rattaches++;
            }

            $agent = trim((string) $h->agent);
            $comptes = $parNom[$agent] ?? [];
            $auteur = count($comptes) === 1 && isset($existants[$comptes[0]['id']]) ? $comptes[0] : null;
            if ($agent !== '' && $comptes === []) {
                $auteursInconnus[$agent] = ($auteursInconnus[$agent] ?? 0) + 1;
            } elseif (count($comptes) > 1) {
                $auteursAmbigus[$agent] = ['n' => ($auteursAmbigus[$agent]['n'] ?? 0) + 1, 'ids' => array_column($comptes, 'id')];
            }

            $date = self::date($h->dateadd);

            if ($commandId !== null && $date !== null) {
                $this->noterJalon($jalons, $commandId, CarteStatuts::cle($etat), $date);
            }

            $lot[] = [
                'legacy_history_id' => (int) $h->id,
                'command_id' => $commandId,
                'command_code' => $code,
                'from_state' => $etatPrecedent,
                'to_state' => $etat,
                'to_statut_id' => $statuts[$etat] ?? null,
                'changed_by_type' => $auteur === null ? null : match ($auteur['type']) {
                    'dlm' => 'livreur', 'moderator' => 'moderateur', 'client' => 'client', default => $auteur['type'],
                },
                'changed_by_id' => $auteur['id'] ?? null,
                'changed_by_nom' => $agent === '' ? null : $agent,
                'change_source' => 'legacy',
                'metadata' => null,
                'created_at' => $date,
            ];
            $etatPrecedent = $etat;

            if (count($lot) >= $this->taille()) {
                $this->ecrire($lot);
                $lot = [];
                if ($total % 20000 < $this->taille()) {
                    $this->info("  historique : {$total}");
                }
            }
        }
        $this->ecrire($lot);
        if ($mysql) {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        foreach ($sansColis as $code => $n) {
            $this->journal->signaler('historique', 'historique_sans_colis', 'attention', "{$n} ligne(s) d’historique pour le code « {$code} » qui ne correspond à aucun colis", 'commandshistory', null, $code, ['lignes' => $n]);
        }
        foreach ($ambigus as $code => $n) {
            $this->journal->signaler('historique', 'historique_code_ambigu', 'attention', "{$n} ligne(s) d’historique pour le code « {$code} », porté par ".count($parCode[$code]).' colis : non rattachées', 'commandshistory', null, $code, ['lignes' => $n, 'colis_ids' => $parCode[$code]]);
        }
        foreach ($auteursInconnus as $nom => $n) {
            $this->journal->signaler('historique', 'auteur_inconnu', 'info', "Auteur « {$nom} » ({$n} changements d’état) : aucun compte de l’ancien site ne porte ce nom", 'commandshistory', null, $nom, ['lignes' => $n]);
        }
        foreach ($auteursAmbigus as $nom => $a) {
            $this->journal->signaler('historique', 'auteur_ambigu', 'info', "Auteur « {$nom} » ({$a['n']} changements d’état) : plusieurs comptes portent ce nom (#".implode(', #', $a['ids']).')', 'commandshistory', null, $nom, ['lignes' => $a['n'], 'comptes' => $a['ids']]);
        }

        $majJalons = $this->appliquerJalons($jalons);
        $this->journal->cloturer(self::domaines());

        return ['lignes' => $total, 'rattachees' => $rattaches, 'codes_sans_colis' => count($sansColis), 'codes_ambigus' => count($ambigus), 'colis_jalons_modifies' => $majJalons];
    }

    /** @param array<int, array<string, string>> $jalons */
    private function noterJalon(array &$jalons, int $id, string $cle, string $date): void
    {
        $min = function (string $col) use (&$jalons, $id, $date) {
            if (! isset($jalons[$id][$col]) || $date < $jalons[$id][$col]) {
                $jalons[$id][$col] = $date;
            }
        };
        $max = function (string $col) use (&$jalons, $id, $date) {
            if (! isset($jalons[$id][$col]) || $date > $jalons[$id][$col]) {
                $jalons[$id][$col] = $date;
            }
        };

        if (in_array($cle, CarteStatuts::JALON_LIVRE, true)) {
            $max('livre_at');
        } elseif (in_array($cle, CarteStatuts::JALON_RAMASSE, true)) {
            $min('ramasse_at');
        } elseif (in_array($cle, CarteStatuts::JALON_EXPEDIE, true)) {
            $min('expedie_at');
        } elseif (in_array($cle, CarteStatuts::JALON_RETOUR_CLIENT, true)) {
            $max('retour_recu_client_at');
        } elseif (in_array($cle, CarteStatuts::JALON_RETOUR_AGENCE, true)) {
            $min('retour_agence_at');
        }
    }

    /** @param list<array<string, mixed>> $lot */
    private function ecrire(array $lot): void
    {
        if ($this->simulation || $lot === []) {
            return;
        }
        foreach (array_chunk($lot, 500) as $morceau) {
            DB::table('command_status_histories')->upsert($morceau, ['legacy_history_id'], [
                'command_id', 'command_code', 'from_state', 'to_state', 'to_statut_id',
                'changed_by_type', 'changed_by_id', 'changed_by_nom', 'change_source', 'created_at',
            ]);
        }
    }

    /**
     * Écrit les jalons sur TOUS les colis repris : une valeur sans événement réel est remise à NULL
     * (une reprise précédente avait daté « livré » à la dernière modification).
     *
     * @param  array<int, array<string, string>>  $jalons
     */
    private function appliquerJalons(array $jalons): int
    {
        $modifies = 0;
        $sansLivre = [];

        foreach (DB::table('commands')->select(array_merge(['id', 'code', 'state_group'], self::JALONS))->orderBy('id')->cursor() as $c) {
            $cible = [];
            foreach (self::JALONS as $col) {
                $cible[$col] = $jalons[$c->id][$col] ?? null;
            }
            if ($c->state_group !== 'livre') {
                $cible['livre_at'] = null;
            } elseif ($cible['livre_at'] === null) {
                $sansLivre[] = (int) $c->id;
            }

            $change = [];
            foreach ($cible as $col => $val) {
                $actuel = $c->{$col} === null ? null : substr((string) $c->{$col}, 0, 19);
                if ($actuel !== $val) {
                    $change[$col] = $val;
                }
            }
            if ($change !== []) {
                $modifies++;
                if (! $this->simulation) {
                    DB::table('commands')->where('id', $c->id)->update($change);
                }
            }
        }

        if ($sansLivre !== []) {
            $this->journal->signaler('historique', 'livre_sans_evenement', 'attention', count($sansLivre).' colis « Livré » sans événement « Livré » dans l’historique : date de livraison inconnue', 'commands', null, 'livre', ['colis' => count($sansLivre), 'colis_ids' => $sansLivre]);
        }

        return $modifies;
    }
}
