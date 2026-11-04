<?php

declare(strict_types=1);

namespace App\Domain\Reprise;

use Illuminate\Support\Carbon;

/**
 * Découpe `commands.note` de l'ancien site en messages.
 *
 * Formats constatés dans la base (profil du 04/11/2026, 50 298 notes) :
 *  ① `<b>Livreur: </b>texte<br />`              (Livreur, Modérateur, Client) — 46 419 notes
 *  ② `&lt;p&gt;&lt;strong&gt;Nom: &lt;/strong&gt;texte&lt;ins&gt;30/03/26 11:27&lt;/ins&gt;&lt;/p&gt;`
 *     message échangé avec le réseau partenaire : auteur nommé ET date écrite dans la note
 *  ③ `&amp;lt;b&amp;gt;Client: &amp;lt;/b&amp;gt;…&amp;lt;br /&amp;gt;` (①, doublement échappé)
 *  ④ texte libre (avant, entre ou sans balises) — auteur inconnu
 *
 * Rien n'est deviné : seul ② porte une date (date_exacte = true) ; les autres messages restent
 * sans date. Un fragment contenant encore du balisage non reconnu est rendu tel quel et signalé.
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
        $note = (string) $note;

        if (trim($note) === '') {
            return ['messages' => [], 'fragments_non_interpretes' => []];
        }

        $motif = '~'
            .'<b>\s*(?<r1>[^<:]{1,40}):\s*</b>(?<t1>.*?)<br\s*/?>'
            .'|&lt;p&gt;&lt;strong&gt;(?<a2>.*?):\s*&lt;/strong&gt;(?<t2>.*?)&lt;ins&gt;(?<d2>\d{2}/\d{2}/\d{2}\s+\d{2}:\d{2})&lt;/ins&gt;&lt;/p&gt;'
            .'|&amp;lt;b&amp;gt;\s*(?<r3>[^&:]{1,40}):\s*&amp;lt;/b&amp;gt;(?<t3>.*?)&amp;lt;br\s*/&amp;gt;'
            .'~su';

        $position = 0;
        preg_match_all($motif, $note, $trouves, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        foreach ($trouves as $m) {
            [$bloc, $offset] = $m[0];
            self::texteLibre(substr($note, $position, $offset - $position), $messages, $restes);
            $position = $offset + strlen($bloc);

            if (($m['r1'][0] ?? null) !== null) {
                self::ajouter($messages, self::role($m['r1'][0]), self::role($m['r1'][0]) === 'inconnu' ? trim($m['r1'][0]) : null, $m['t1'][0], null);
            } elseif (($m['a2'][0] ?? null) !== null) {
                $date = Carbon::createFromFormat('d/m/y H:i', preg_replace('/\s+/', ' ', $m['d2'][0]), $fuseau);
                self::ajouter($messages, 'externe', self::texte($m['a2'][0]), $m['t2'][0], $date ?: null);
            } else {
                self::ajouter($messages, self::role($m['r3'][0]), null, $m['t3'][0], null);
            }
        }

        self::texteLibre(substr($note, $position), $messages, $restes);

        return ['messages' => $messages, 'fragments_non_interpretes' => $restes];
    }

    /** @param list<array<string, mixed>> $messages */
    private static function ajouter(array &$messages, string $role, ?string $auteur, string $texte, ?Carbon $date): void
    {
        $texte = self::texte($texte);
        if ($texte === '') {
            return; // balise vide (« Client: » sans texte) : rien à reprendre, la note d'origine reste sur le colis
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
        if ($texte === '') {
            return;
        }
        if (preg_match('~</?[a-z][^>]*>|&lt;/?[a-z]~i', $brut) === 1) {
            $restes[] = $brut;
        }
        $messages[] = ['role' => 'inconnu', 'auteur_nom' => null, 'message' => $texte, 'date' => null];
    }

    private static function texte(string $brut): string
    {
        $s = $brut;
        for ($i = 0; $i < 3 && str_contains($s, '&'); $i++) {
            $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $s = (string) preg_replace('~<br\s*/?>~i', "\n", $s);
        $s = strip_tags($s);

        return trim((string) preg_replace("/[ \t]+/u", ' ', $s));
    }

    private static function role(string $libelle): string
    {
        return self::ROLES[CarteStatuts::cle($libelle)] ?? 'inconnu';
    }
}
