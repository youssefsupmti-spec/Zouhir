<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\Actions\RegisterClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Affiche l'écran de connexion.
     */
    public function showLogin(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Traite la demande d'authentification.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', 'Bienvenue sur votre espace OUAISS EXPRESS !');
    }

    /**
     * Affiche l'écran d'inscription client.
     */
    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Traite la création d'un nouveau compte client.
     */
    public function register(RegisterRequest $request, RegisterClientAction $action): RedirectResponse
    {
        $user = $action->execute($request->validated());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect(route('dashboard'))->with('success', 'Votre compte OUAISS EXPRESS a été créé avec succès.');
    }

    /**
     * Déconnexion sécurisée.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
