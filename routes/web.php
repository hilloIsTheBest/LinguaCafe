<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\Admin\OIDCController as AdminOIDCController;
use App\Http\Controllers\PublicLibraryController;
use App\Http\Controllers\BookController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Application routes.
|
*/

// Home (requires auth)
Route::get('/', [HomeController::class, 'index'])->middleware('auth')->name('home');

// Login/Logout
Route::get('/login', [UserController::class, 'showLoginForm'])->name('login');
Route::post('/login', [UserController::class, 'authenticateUser']);
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

// User helpers
Route::get('/users/is-password-changed', [UserController::class, 'isUserPasswordChanged'])->middleware('auth');
Route::post('/users/update-password', [UserController::class, 'updatePassword'])->middleware('auth');

/*
|--------------------------------------------------------------------------
| Settings API
|--------------------------------------------------------------------------
*/
Route::post('/settings/global/get', [SettingsController::class, 'getGlobalSettingsByName'])->middleware('web');
Route::post('/settings/global/update', [SettingsController::class, 'updateGlobalSettings'])->middleware('auth');
Route::post('/settings/user/get', [SettingsController::class, 'getUserSettingsByName'])->middleware('auth');
Route::post('/settings/user/update', [SettingsController::class, 'updateUserSettings'])->middleware('auth');
Route::get('/settings/is-jellyfin-enabled', [SettingsController::class, 'isJellyfinEnabled'])->middleware('auth');
Route::get('/settings/get-anki-settings', [SettingsController::class, 'getAnkiSettings'])->middleware('auth');
Route::post('/settings/branding/upload-icon', [SettingsController::class, 'uploadBrandIcon'])->middleware('auth');

/*
|--------------------------------------------------------------------------
| OIDC (OpenID Connect)
|--------------------------------------------------------------------------
*/
Route::get('/auth/oidc', [OidcController::class, 'redirect'])->name('oidc.redirect');
Route::get('/auth/oidc/callback', [OidcController::class, 'callback'])->name('oidc.callback');
Route::get('/auth/oidc/logout', [OidcController::class, 'logout'])->name('oidc.logout');

// Admin OIDC health diagnostics (JSON)
Route::get('/admin/oidc/health', [AdminOIDCController::class, 'health'])->middleware('auth');

/*
|--------------------------------------------------------------------------
| Playlists
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['auth']], function () {
    Route::get('/playlists', [PlaylistController::class, 'listPlaylists']);
    Route::post('/playlists/create', [PlaylistController::class, 'createPlaylist']);
    Route::post('/playlists/delete/{playlistId}', [PlaylistController::class, 'deletePlaylist']);
    Route::get('/playlists/items/{playlistId}', [PlaylistController::class, 'listItems']);
    Route::post('/playlists/items/add', [PlaylistController::class, 'addItem']);
    Route::post('/playlists/items/remove', [PlaylistController::class, 'removeItem']);
    Route::post('/playlists/items/move', [PlaylistController::class, 'moveItem']);
    Route::get('/playlists/items/next', [PlaylistController::class, 'nextItem']);
});

/*
|--------------------------------------------------------------------------
| Books (subset used on UI paths referenced)
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['auth']], function () {
    Route::get('/books', [BookController::class, 'getBooks']);
    Route::post('/books', [BookController::class, 'createBook']);
    Route::post('/books/delete', [BookController::class, 'deleteBook']);
    Route::get('/books/get-word-counts/{bookId}', [BookController::class, 'getBookWordCounts']);
    Route::get('/books/details/{bookId}', [BookController::class, 'getBookDetails']);
});

/*
|--------------------------------------------------------------------------
| Public Library
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['auth']], function () {
    Route::get('/public/books', [PublicLibraryController::class, 'listPublicBooks']);
    Route::post('/public/books/copy/{bookId}', [PublicLibraryController::class, 'copyPublicBook']);
});

/*
|--------------------------------------------------------------------------
| SPA admin shell (serve Vue app for admin URLs)
|--------------------------------------------------------------------------
*/
Route::get('/admin/{any?}', [HomeController::class, 'index'])
    ->where('any', '.*')
    ->middleware('auth');
