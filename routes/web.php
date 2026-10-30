<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Ouais Express (Client: Zouhir)
|--------------------------------------------------------------------------
| Phase 1: Landing Page, Authentification & Inscription uniquement.
*/

// Vitrine & Outils publics
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::post('/api/tarification/calculate', [LandingController::class, 'calculateRate'])->name('tarification.calculate');
Route::post('/api/tracking/search', [LandingController::class, 'trackParcel'])->name('tracking.search');

// Authentification Invités (Redirige vers /dashboard si déjà connecté)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Espace Membres Authentifiés (Redirige vers /login si non connecté)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // 5 Référentiels Métier & Pages de Gestion (Sprint 1)
    Route::get('/businesses', [\App\Http\Controllers\BusinessController::class, 'index'])->name('businesses.index');
    Route::get('/clients', [\App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::get('/livreurs', [\App\Http\Controllers\LivreurController::class, 'index'])->name('livreurs.index');
    Route::get('/villes', [\App\Http\Controllers\VilleController::class, 'index'])->name('villes.index');
    Route::get('/banks', [\App\Http\Controllers\BankController::class, 'index'])->name('banks.index');
});
