<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\ReviewController;

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);


/*
|--------------------------------------------------------------------------
| PRODUITS PUBLICS
|--------------------------------------------------------------------------
|
| Ces routes peuvent être utilisées même sans connexion.
|
*/

Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{product}', [ProductController::class, 'show']);


/*
|--------------------------------------------------------------------------
| ROUTES PROTÉGÉES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'last.seen'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DÉCONNEXION
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);


    /*
    |--------------------------------------------------------------------------
    | PRODUITS
    |--------------------------------------------------------------------------
    */

    Route::get('/products', [ProductController::class, 'index']);


    /*
    |--------------------------------------------------------------------------
    | ADMIN - GESTION DES PRODUITS
    |--------------------------------------------------------------------------
    */

    Route::middleware('admin')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | DASHBOARD ADMIN
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/admin/dashboard',
            [AdminDashboardController::class, 'dashboard']
        );


        /*
        |--------------------------------------------------------------------------
        | GESTION DES PRODUITS
        |--------------------------------------------------------------------------
        */

        Route::post('/products', [ProductController::class, 'store']);

        Route::put(
            '/products/{product}',
            [ProductController::class, 'update']
        );

        Route::delete(
            '/products/{product}',
            [ProductController::class, 'destroy']
        );
    });


    /*
    |--------------------------------------------------------------------------
    | DASHBOARD VENDEUR
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/seller/dashboard',
        [SellerDashboardController::class, 'dashboard']
    );


    /*
    |--------------------------------------------------------------------------
    | AVIS ET ÉVALUATIONS
    |--------------------------------------------------------------------------
    */

    // Voir les avis d'un produit
    Route::get(
        '/products/{product}/reviews',
        [ReviewController::class, 'index']
    );

    // Ajouter un avis à un produit
    Route::post(
        '/products/{product}/reviews',
        [ReviewController::class, 'store']
    );


    /*
    |--------------------------------------------------------------------------
    | COMMANDES
    |--------------------------------------------------------------------------
    */

    Route::post('/orders', [OrderController::class, 'store']);

    Route::get('/orders', [OrderController::class, 'index']);

    Route::get(
        '/orders/{order}',
        [OrderController::class, 'show']
    );

    Route::patch(
        '/orders/{order}/status',
        [OrderController::class, 'updateStatus']
    );


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notifications',
        [NotificationController::class, 'index']
    );

    Route::patch(
        '/notifications/{id}/read',
        [NotificationController::class, 'markAsRead']
    );

    Route::patch(
        '/notifications/read-all',
        [NotificationController::class, 'markAllAsRead']
    );

    Route::delete(
        '/notifications/{id}',
        [NotificationController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | CONVERSATIONS
    |--------------------------------------------------------------------------
    */

    // Liste des administrateurs disponibles
    Route::get(
        '/admins',
        [ConversationController::class, 'admins']
    );

    // Liste des conversations
    Route::get(
        '/conversations',
        [ConversationController::class, 'index']
    );

    // Créer une conversation
    Route::post(
        '/conversations',
        [ConversationController::class, 'store']
    );

    // Afficher une conversation
    Route::get(
        '/conversations/{conversation}',
        [ConversationController::class, 'show']
    );

    // Marquer toute une conversation comme lue
    Route::patch(
        '/conversations/{conversation}/read',
        [MessageController::class, 'markConversationAsRead']
    );

    // Supprimer toute une conversation
    Route::delete(
        '/conversations/{conversation}',
        [ConversationController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | MESSAGES
    |--------------------------------------------------------------------------
    */

    // Liste des messages
    Route::get(
        '/conversations/{conversation}/messages',
        [MessageController::class, 'index']
    );

    // Envoyer un message
    Route::post(
        '/conversations/{conversation}/messages',
        [MessageController::class, 'store']
    );

    // Marquer un message comme lu
    Route::patch(
        '/messages/{message}/read',
        [MessageController::class, 'markAsRead']
    );

    // Supprimer un message
    Route::delete(
        '/messages/{message}',
        [MessageController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | PANIER
    |--------------------------------------------------------------------------
    */

    // Voir mon panier
    Route::get(
        '/cart',
        [CartController::class, 'index']
    );

    // Ajouter au panier
    Route::post(
        '/cart',
        [CartController::class, 'store']
    );

    // Modifier une quantité
    Route::patch(
        '/cart/items/{item}',
        [CartController::class, 'update']
    );

    // Supprimer un article
    Route::delete(
        '/cart/items/{item}',
        [CartController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | BOUTIQUES
    |--------------------------------------------------------------------------
    */

    // Voir toutes les boutiques
    Route::get(
        '/shops',
        [ShopController::class, 'all']
    );

    // Voir ma boutique
    Route::get(
        '/shop',
        [ShopController::class, 'index']
    );

    // Créer ma boutique
    Route::post(
        '/shop',
        [ShopController::class, 'store']
    );

    // Voir une boutique précise
    Route::get(
        '/shops/{shop}',
        [ShopController::class, 'show']
    );
});


/*
|--------------------------------------------------------------------------
| UTILISATEUR CONNECTÉ
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {

    return response()->json([
        'user' => $request->user(),
    ]);
});