<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlaylistController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::resource('playlists', PlaylistController::class)->middleware('auth');

/*
|--------------------------------------------------------------------------
| Removed conflicting routes:
|--------------------------------------------------------------------------
|
| The following custom routes were removed to avoid conflicts with the
| resourceful controller:
|
| Route::get('/listPlaylists', [PlaylistController::class, 'index']);
| Route::post('/addItem', [PlaylistController::class, 'store']);
| ... (any other non‑resource routes)
|
*/
