<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('wablas/webhook', [\App\Http\Controllers\WablasController::class, 'webhook']);
Route::post('moota/webhook', [\App\Http\Controllers\MootaController::class, 'webhook']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

