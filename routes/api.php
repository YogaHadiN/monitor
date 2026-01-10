<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KommoWebhookController;
use App\Http\Controllers\BarantumWebhookController;

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

Route::post('/kommo/webhook', [KommoWebhookController::class, 'handle']);
Route::post('/barantum/webhook', [BarantumWebhookController::class, 'handle']);

/* Route::get('wablas/webhook', [\App\Http\Controllers\BotCakeController::class, 'webhookGet']); */
/* Route::get('wablas/webhook', [\App\Http\Controllers\WablasController::class, 'webhook']); */
/* Route::post('wablas/webhook', [\App\Http\Controllers\WablasController::class, 'webhook']); */

/* Route::get('qiscus/webhook', [\App\Http\Controllers\WablasController::class, 'webhookGet']); */
/* Route::post('qiscus/webhook', [\App\Http\Controllers\WablasController::class, 'webhook']); */

/* Route::get('qiscus/webhook', [\App\Http\Controllers\QiscusController::class, 'webhookGet']); */
/* Route::post('qiscus/webhook', [\App\Http\Controllers\QiscusController::class, 'webhook']); */


/* Route::post('wablas/webhookcs', [\App\Http\Controllers\WablasEstetikController::class, 'webhook']); */
/* Route::post('moota/webhook', [\App\Http\Controllers\MootaController::class, 'webhook']); */

Route::get('webhook/wablas', [\App\Http\Controllers\WablasWebhookController::class, 'wablas']);
Route::post('webhook/wablas', [\App\Http\Controllers\WablasWebhookController::class, 'wablas']);


Route::get('fonnte/webhook', [\App\Http\Controllers\FonnteController::class, 'getWebhook']);
Route::get('fonnte/webhook/status', [\App\Http\Controllers\FonnteController::class, 'getStatus']);
Route::get('fonnte/webhook/connect', [\App\Http\Controllers\FonnteController::class, 'getConnect']);
Route::get('fonnte/webhook/chaining', [\App\Http\Controllers\FonnteController::class, 'getChaning']);
Route::post('fonnte/webhook', [\App\Http\Controllers\FonnteController::class, 'postWebhook']);
Route::post('fonnte/webhook/status', [\App\Http\Controllers\FonnteController::class, 'postStatus']);
Route::post('fonnte/webhook/connect', [\App\Http\Controllers\FonnteController::class, 'postConnect']);
Route::post('fonnte/webhook/chaining', [\App\Http\Controllers\FonnteController::class, 'postChaining']);

/* Route::get('fonnte_2/webhook', [\App\Http\Controllers\FonnteSecondController::class, 'getWebhook']); */
/* Route::get('fonnte_2/webhook/status', [\App\Http\Controllers\FonnteSecondController::class, 'getStatus']); */
/* Route::get('fonnte_2/webhook/connect', [\App\Http\Controllers\FonnteSecondController::class, 'getConnect']); */
/* Route::get('fonnte_2/webhook/chaining', [\App\Http\Controllers\FonnteSecondController::class, 'getChaning']); */
/* Route::post('fonnte_2/webhook', [\App\Http\Controllers\FonnteSecondController::class, 'postWebhook']); */
/* Route::post('fonnte_2/webhook/status', [\App\Http\Controllers\FonnteSecondController::class, 'postStatus']); */
/* Route::post('fonnte_2/webhook/connect', [\App\Http\Controllers\FonnteSecondController::class, 'postConnect']); */
/* Route::post('fonnte_2/webhook/chaining', [\App\Http\Controllers\FonnteSecondController::class, 'postChaining']); */

Route::get('antrian_online/bpjs/auth', [\App\Http\Controllers\AntrianOnlineController::class, 'token']);

Route::get("antrian_online/bpjs/ref/poli/tanggal/{tanggal}", [
    \App\Http\Controllers\AntrianOnlineController::class,
    "status_antrean"
]);



Route::group([
    "middleware" => ["auth.jwt"]
], function(){

    Route::get("antrian_online/bpjs/antrean/status/{kode_poli}/{tanggalperiksa}", [
        \App\Http\Controllers\AntrianOnlineController::class,
        "status_antrean"
    ]);

    Route::post("antrian_online/bpjs/antrean", [
        \App\Http\Controllers\AntrianOnlineController::class,
        "ambil_antrean"
    ]);

    Route::get("antrian_online/bpjs/antrean/sisapeserta/{nomorkartu_jkn}/{kode_poli}/{tanggalperiksa}", [
        \App\Http\Controllers\AntrianOnlineController::class,
        "sisa_antrean"
    ]);

    Route::post("antrian_online/bpjs/peserta", [
        \App\Http\Controllers\AntrianOnlineController::class,
        "pasien_baru"
    ]);

    Route::put("antrian_online/bpjs/antrean/batal", [
        \App\Http\Controllers\AntrianOnlineController::class,
        "batal_antrean"
    ]);

});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

