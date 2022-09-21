<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AntrianController;
use App\Http\Controllers\ValidateController;
use App\Http\Controllers\PasienController;

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

Route::get('phpinfo', function(){
    phpinfo();
});
Route::get('/', [AntrianController::class, 'index']);
Route::get('antrianperiksa/monitor', [AntrianController::class, 'monitor']);
/* Route::post('wablas/webhook', [\App\Http\Controllers\WablasController::class, 'wablas2']); */
Route::post('wablas/webhook', [\App\Http\Controllers\WablasController::class, 'webhook']);
Route::post('moota/webhook', [\App\Http\Controllers\MootaController::class, 'webhook']);
Route::get('antrianperiksa/monitor/convert_sound_to_array', [AntrianController::class, 'convertSoundToArray']);
Route::get('validasi/antigen/{id}', [ValidateController::class, 'antigen']);
Route::get('validasi/antibodi/{id}', [ValidateController::class, 'antibodi']);
Route::get('validasi/surat_sakit/{id}', [ValidateController::class, 'surat_sakit']);
Route::get('antrianperiksa/fail', [AntrianController::class, 'fail']);
Route::get('antrianperiksa/{id}', [AntrianController::class, 'antri']);
Route::get('eksklusi/{id}', [PasienController::class, 'eksklusi']);
Route::get('antrianperiksa/monitor/getData/{panggil_pasien}', [AntrianController::class, 'updateJumlahAntrian']);

