<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * La production tourne en APP_LOCALE=fr : sans lang/fr, l'utilisateur voyait « validation.required » ou « auth.failed ».
 */
class TraductionsFrancaisesTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucune_cle_brute_en_francais_pour_les_regles_utilisees(): void
    {
        app()->setLocale('fr');

        $v = Validator::make(
            ['email' => 'pas-un-mail', 'zone_id' => 'x', 'nom' => str_repeat('a', 300), 'actif' => 'peut-etre', 'liste' => 'x'],
            ['tracking_code' => 'required', 'email' => 'email', 'zone_id' => 'integer', 'nom' => 'max:255', 'actif' => 'boolean', 'liste' => 'array'],
        );

        $messages = $v->errors()->all();
        $this->assertCount(6, $messages);
        foreach ($messages as $m) {
            $this->assertStringNotContainsString('validation.', $m);
        }
        $this->assertSame('Le champ code de suivi est obligatoire.', $v->errors()->first('tracking_code'));
        $this->assertSame('Le champ zone doit être un nombre entier.', $v->errors()->first('zone_id'));
    }

    public function test_messages_d_authentification_traduits(): void
    {
        app()->setLocale('fr');

        $this->assertSame('Ces identifiants ne correspondent à aucun compte.', __('auth.failed'));
        $this->assertStringContainsString('30 secondes', trans('auth.throttle', ['seconds' => 30]));
    }

    public function test_connexion_echouee_affiche_un_message_francais(): void
    {
        app()->setLocale('fr');

        $this->from('/login')->post('/login', ['email' => 'inconnu@example.com', 'password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors();

        foreach (session('errors')->getBag('default')->all() as $m) {
            $this->assertStringNotContainsString('auth.', $m);
            $this->assertStringNotContainsString('validation.', $m);
        }
    }

    public function test_l_anglais_reste_disponible(): void
    {
        app()->setLocale('en');

        $this->assertSame('The tracking code field is required.', Validator::make([], ['tracking_code' => 'required'])->errors()->first());
    }
}
