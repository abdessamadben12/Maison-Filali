<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'home'])->name('home');

// SEO : plan du site et consignes pour les robots
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/boutique', [ShopController::class, 'catalogue'])->name('catalogue');
Route::redirect('/catalogue', '/boutique', 301);
Route::redirect('/maison/notre-maison', '/maison/notre-histoire', 301);
Route::redirect('/maison/livraison-echanges', '/maison/livraison-retours', 301);
Route::get('/collection/{category}', [ShopController::class, 'catalogue'])->name('category');
Route::get('/produit/{product}', [ShopController::class, 'product'])->name('product');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:5,1')->name('contact.send');
Route::get('/maison/{slug}', [ShopController::class, 'page'])->name('page');

Route::post('/commande', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('order.store');
Route::get('/commande/{order}/merci', [OrderController::class, 'thanks'])->name('order.thanks');
Route::get('/suivre-ma-commande', [OrderController::class, 'track'])->name('track');
Route::post('/suivre-ma-commande', [OrderController::class, 'find'])->middleware('throttle:20,1')->name('track.find');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/inscription', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/inscription', [AuthController::class, 'register'])->name('register.store');
});
Route::post('/deconnexion', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('mon-compte')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'orders'])->name('orders');
    Route::get('/commandes/{order}', [AccountController::class, 'order'])->name('order');
    Route::get('/informations', [AccountController::class, 'info'])->name('info');
    Route::put('/informations', [AccountController::class, 'update'])->name('info.update');
});
