<?php

use App\Http\Controllers\PaydunyaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccueilController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TypeCategorieController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\GammeController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ProduitSportController;
use App\Http\Controllers\SportController;
use App\Http\Controllers\PanierController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\LivreurController;
use App\Http\Controllers\VendeurController;
use App\Http\Controllers\TemoignageController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\FactureController;

// ─── Auth ─────────────────────────────────────────────────────
Route::post('/login',   [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// ─── Routes publiques ─────────────────────────────────────────
Route::get('/accueil',                [AccueilController::class, 'index']);
Route::get('/accueil/search',         [AccueilController::class, 'search']);
Route::get('/accueil/categorie/{id}', [AccueilController::class, 'filterByCategorie']);
Route::get('/accueil/gamme/{id}',     [AccueilController::class, 'filterByGamme']);
Route::get('/accueil/type-categorie/{nom}', [AccueilController::class, 'filterByTypeCategorie']);

Route::get('/sport',       [SportController::class, 'index']);
Route::get('/temoignages', [TemoignageController::class, 'showPublic']);
Route::get('/temoignages-public', [TemoignageController::class, 'showPublic']);


//Route::apiResource('typecategories', TypeCategorieController::class);
//Route::apiResource('categories',     CategorieController::class);
//Route::apiResource('gammes',         GammeController::class);
//Route::apiResource('produits',       ProduitController::class);
//Route::apiResource('produits-sport', ProduitSportController::class);
Route::get('/produits-sport/{id}/medias', [ProduitSportController::class, 'getMedias']);
//Route::apiResource('boutiques', BoutiqueController::class);

// ─── Checkout public (guests + connectés) ─────────────────────
Route::post('/checkout',                           [CheckoutController::class, 'process']);
Route::get('/checkout/confirmation/{orderNumber}', [CheckoutController::class, 'confirmation']);
Route::get('/checkout/status/{orderNumber}',       [CheckoutController::class, 'checkOrderStatus']);
Route::get('/checkout/whatsapp/{orderNumber}',     [CheckoutController::class, 'getWhatsAppMessage']);
// PDF
Route::get('/checkout/pdf/{orderNumber}', [CheckoutController::class, 'generatePDF']);
// ─── Authentifié avec JWT ─────────────────────────────────────
//Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    // Panier — fixes AVANT {id}
    Route::get('/panier/count',   [PanierController::class, 'count']);
    Route::post('/panier/vider',  [PanierController::class, 'viderPanier']);
    Route::get('/panier',         [PanierController::class, 'index']);
    Route::post('/panier',        [PanierController::class, 'store']);
    Route::put('/panier/{id}',    [PanierController::class, 'update']);
    Route::delete('/panier/{id}', [PanierController::class, 'destroy']);

    // SMS
    Route::post('/sms/send',   [SmsController::class, 'sendSms']);
    Route::get('/sms/balance', [SmsController::class, 'getSmsBalance']);

    // ─── Admin ────────────────────────────────────────────────
//    Route::prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Catalogue
        Route::apiResource('typecategories', TypeCategorieController::class);
        Route::apiResource('categories',     CategorieController::class);
        Route::apiResource('gammes',         GammeController::class);
        Route::apiResource('produits',       ProduitController::class);
        Route::apiResource('produits-sport', ProduitSportController::class);
        Route::get('produits-sport/{id}/medias', [ProduitSportController::class, 'getMedias']);
        Route::get('/produits-sport/type-categorie/{nom}', [ProduitSportController::class, 'filterByTypeCategorie']);
        Route::apiResource('factures', FactureController::class);
        // Route::get('factures/{facture}/download', [FactureController::class, 'download'])->name('factures.download');

        // Commandes — fixes AVANT apiResource
        Route::get('/commandes/export/csv', [CommandeController::class, 'export']);
        Route::get('/commandes/statistics', [CommandeController::class, 'statistics']);
        Route::apiResource('commandes', CommandeController::class)->except(['store', 'create', 'edit']);

        // Clients — fixes AVANT apiResource
        Route::patch('/clients/{client}/verify-email', [ClientController::class, 'verifyEmail']);
        Route::get('/clients/{client}/stats',          [ClientController::class, 'stats']);
        Route::patch('/clients/{client}/statut',       [ClientController::class, 'toggleStatut']);
        Route::apiResource('clients', ClientController::class);

        // Personnel — fix AVANT apiResource
        Route::patch('/vendeurs/{id}/change-role', [VendeurController::class, 'changeRole']);
        Route::apiResource('livreurs',  LivreurController::class);
        Route::apiResource('vendeurs',  VendeurController::class);
        Route::apiResource('boutiques', BoutiqueController::class);

        // Témoignages
        Route::apiResource('temoignages', TemoignageController::class);
//    });

//});
//////////////////////////  TEST AVEC PAYDUNYA ///////////////////////////////////////////////////////////////////////////////////
Route::get('/test-paydunya-config', function() {
    return [
        'mode' => config('paydunya.mode'),
        'public_key' => substr(config('paydunya.test.public_key'), 0, 10) . '...',
        'private_key' => substr(config('paydunya.test.private_key'), 0, 10) . '...',
        'store_name' => config('paydunya.store.name'),
    ];
});
Route::post('/paydunya/init/{commandeId}', [PaydunyaController::class, 'initPayment']);
Route::post('/checkout/init', [CheckoutController::class, 'initCheckout']);
Route::post('/checkout/confirm-payment', [CheckoutController::class, 'confirmPaymentAndCreateOrder']);
