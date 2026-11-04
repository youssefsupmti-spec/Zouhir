<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use App\Domain\Reprise\NoteLegacyParser;
use Illuminate\Support\Facades\DB;

/**
 * Notes : `commands.note` (gardée intacte sur le colis) → `note_messages` (source « legacy »).
 * Rejouable : les messages legacy d'un colis sont remplacés à chaque passage ; ceux saisis dans la
 * plateforme (source « application ») ne sont jamais touchés.
 */
final class RepriseNotes extends EtapeReprise
{
    public static function nom(): string
    {
        return 'notes';
    }

    public static function domaines(): array
    {
        return ['notes'];
    }

    public function executer(): array
    {
        $parNom = [];
        foreach ($this->legacy->table('users')->get(['id', 'fullname']) as $u) {
            $parNom[mb_strtolower(trim((string) $u->fullname))][] = (int) $u->id;
        }
        $existants = DB::table('users')->pluck('id', 'id');

        $notes = 0;
        $messages = 0;
        $sansAuteur = [];
        $fuseau = (string) config('app.timezone', 'UTC');

        DB::table('commands')->whereNotNull('note')->where('note', '<>', '')->select(['id', 'code', 'note', 'created_at'])
            ->orderBy('id')->chunkById($this->taille(), function ($lot) use ($parNom, $existants, $fuseau, &$notes, &$messages, &$sansAuteur) {
                $lignes = [];
                foreach ($lot as $c) {
                    $notes++;
                    $analyse = NoteLegacyParser::analyser($c->note, 'Africa/Casablanca');
                    foreach ($analyse['fragments_non_interpretes'] as $fragment) {
                        $this->journal->signaler('notes', 'fragment_non_interprete', 'attention', "Colis {$c->code} : partie de note au format inconnu (reprise en texte brut)", 'commands', $c->id, mb_substr($fragment, 0, 500), [], 'commands', (int) $c->id);
                    }
                    foreach ($analyse['messages'] as $i => $m) {
                        if ($m['role'] === 'inconnu' && $m['auteur_nom'] === null) {
                            $sansAuteur[] = (int) $c->id;
                        }
                        $ids = $m['auteur_nom'] !== null ? ($parNom[mb_strtolower($m['auteur_nom'])] ?? []) : [];
                        $userId = count($ids) === 1 && isset($existants[$ids[0]]) ? $ids[0] : null;
                        $date = $m['date']?->copy()->setTimezone($fuseau)->toDateTimeString();
                        $lignes[] = [
                            'command_id' => (int) $c->id,
                            'user_id' => $userId,
                            'role' => $m['role'],
                            'auteur_nom' => $m['auteur_nom'],
                            'message' => $m['message'],
                            'position' => $i,
                            'source' => 'legacy',
                            'date_exacte' => $date !== null,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ];
                    }
                }
                $messages += count($lignes);

                if (! $this->simulation) {
                    DB::transaction(function () use ($lot, $lignes) {
                        DB::table('note_messages')->where('source', 'legacy')->whereIn('command_id', $lot->pluck('id'))->delete();
                        foreach (array_chunk($lignes, 500) as $morceau) {
                            DB::table('note_messages')->insert($morceau);
                        }
                    });
                }
            });

        $sansAuteur = array_values(array_unique($sansAuteur));
        if ($sansAuteur !== []) {
            $this->journal->signaler('notes', 'note_sans_auteur', 'info', count($sansAuteur).' colis ont une note (ou une partie) sans auteur ni date : reprise comme « auteur inconnu »', 'commands', null, 'texte_libre', ['colis' => count($sansAuteur), 'colis_ids' => $sansAuteur]);
        }
        $this->journal->cloturer(self::domaines());

        return ['notes' => $notes, 'messages' => $messages];
    }
}
