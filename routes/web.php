<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlaylistController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::resource('playlists', PlaylistController::class)->middleware('auth');

/*
|--------------------------------------------------------------------------
| OIDC Routes
|--------------------------------------------------------------------------
|
| These routes handle the OpenID Connect flow.
|
*/
Route::get('/auth/oidc/redirect', [OAuthController::class, 'redirect'])->name('oidc.redirect');
Route::get('/auth/oidc/callback', [OAuthController::class, 'callback'])->name('oidc.callback');

/*
|--------------------------------------------------------------------------
| Admin OIDC Settings
|--------------------------------------------------------------------------
|
| Routes for editing and updating OIDC settings.
|
*/
Route::get('/admin/oidc/edit', [OIDCController::class, 'edit']);
Route::post('/admin/oidc/update', [OIDCController::class, 'update']);

