<?php

declare(strict_types=1);

namespace App\Domain\Reprise;

use Illuminate\Support\Carbon;

/**
 * Découpe `commands.note` de l'ancien site en messages.
 *
 * Formats constatés en production (04/11/2026, 50 304 notes), selon le nombre d'échappements HTML
 * (0, 1 ou 2 fois) — la note est d'abord décodée, puis lue :
 *  ① `<b>Livreur: </b>texte<br />`            (Livreur, Modérateur, Client)
 *  ② `<p><strong>Nom: </strong>texte<ins>30/03/26 11:27</ins></p>`
 *     message échangé avec le réseau partenaire : auteur nommé ET date écrite (année sur 2 ou 4 chiffres)
 *  ③ texte libre (avant, entre ou sans balises) — auteur inconnu
 *
 * Rien n'est deviné : seul ② porte une date (date_exacte = true). Un fragment contenant encore du
 * balisage après lecture est repris en texte brut et signalé. La note d'origine reste sur le colis.
 */
final class NoteLegacyParser
{
    private const ROLES = ['livreur' => 'livreur', 'moderateur' => 'moderateur', 'client' => 'client'];

    /**
     * @return array{messages: list<array{role: string, auteur_nom: ?string, message: string, date: ?Carbon}>, fragments_non_interpretes: list<string>}
     */
    public static function analyser(?string $note, string $fuseau = 'Africa/Casablanca'): array
    {
        $messages = [];
        $restes = [];
        $note = self::decoder((string) $note);

        if (trim($note) === '') {
            return ['messages' => [], 'fragments_non_interpretes' => []];
        }

        $motif = '~'
            .'<b>\s*(?<r1>[^<:]{1,40}):\s*</b>(?<t1>.*?)<br\s*/?>'
            .'|<p>\s*<strong>(?<a2>[^<]*?):\s*</strong>(?<t2>.*?)<ins>\s*(?<d2>\d{2}/\d{2}/\d{2,4}\s+\d{2}:\d{2})\s*</ins>\s*</p>'
            .'~su';

        $position = 0;
        preg_match_all($motif, $note, $trouves, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        foreach ($trouves as $m) {
            [$bloc, $offset] = $m[0];
            self::texteLibre(substr($note, $position, $offset - $position), $messages, $restes);
            $position = $offset + strlen($bloc);

            if (($m['r1'][0] ?? null) !== null) {
                $role = self::role($m['r1'][0]);
                self::ajouter($messages, $role, $role === 'inconnu' ? trim($m['r1'][0]) : null, $m['t1'][0], null);
            } else {
                $brut = (string) preg_replace('/\s+/', ' ', $m['d2'][0]);
                $format = strlen(explode(' ', explode('/', $brut)[2])[0]) === 4 ? 'd/m/Y H:i' : 'd/m/y H:i';
                $date = Carbon::createFromFormat($format, $brut, $fuseau);
                self::ajouter($messages, 'externe', self::texte($m['a2'][0]), $m['t2'][0], $date ?: null);
            }
        }

        self::texteLibre(substr($note, $position), $messages, $restes);

        return ['messages' => $messages, 'fragments_non_interpretes' => $restes];
    }

    /** Annule les échappements successifs (jusqu'à 3) pour retrouver le balisage d'origine. */
    private static function decoder(string $note): string
    {
        for ($i = 0; $i < 3 && preg_match('/&(lt|gt|amp|quot|#0?39);/', $note) === 1; $i++) {
            $note = html_entity_decode($note, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $note;
    }

    /** @param list<array<string, mixed>> $messages */
    private static function ajouter(array &$messages, string $role, ?string $auteur, string $texte, ?Carbon $date): void
    {
        $texte = self::texte($texte);
        if ($texte === '') {
            return; // balise vide (« Client: » sans texte) : rien à reprendre
        }
        $messages[] = ['role' => $role, 'auteur_nom' => $auteur, 'message' => $texte, 'date' => $date];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<string>  $restes
     */
    private static function texteLibre(string $brut, array &$messages, array &$restes): void
    {
        $texte = self::texte($brut);
        if (preg_match('~</?(b|p|strong|ins|br|div|span|i|u|em)\b[^>]*>~i', $brut) === 1) {
            $restes[] = $brut;
        }
        if ($texte === '') {
            return;
        }
        $messages[] = ['role' => 'inconnu', 'auteur_nom' => null, 'message' => $texte, 'date' => null];
    }

    private static function texte(string $brut): string
    {
        $s = (string) preg_replace('~<br\s*/?>~i', "\n", $brut);
        $s = strip_tags($s);

        return trim((string) preg_replace("/[ \t]+/u", ' ', $s));
    }

    private static function role(string $libelle): string
    {
        return self::ROLES[CarteStatuts::cle($libelle)] ?? 'inconnu';
    }
}
