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
Route::get('antrianperiksa/monitor_baru', [AntrianController::class, 'monitor_baru']);
Route::get('fingerprint/register', [AntrianController::class, 'register']);
Route::get('antrianperiksa/monitor/convert_sound_to_array', [AntrianController::class, 'convertSoundToArray']);
Route::get('validasi/antigen/{id}', [ValidateController::class, 'antigen']);
Route::get('validasi/tanda_tangan_staf/{id}', [ValidateController::class, 'tandaTanganStaf']);
Route::get('validasi/antibodi/{id}', [ValidateController::class, 'antibodi']);
Route::get('validasi/surat_sakit/{id}', [ValidateController::class, 'surat_sakit']);
Route::get('antrianperiksa/fail', [AntrianController::class, 'fail']);
Route::get('antrianperiksa/{id}', [AntrianController::class, 'antri']);
Route::get('eksklusi/{id}', [PasienController::class, 'eksklusi']);
Route::get('antrianperiksa/monitor/getData/{panggil_pasien}', [AntrianController::class, 'updateJumlahAntrian']);

Route::get('project/antrian_farmasi', [\App\Http\Controllers\ProjectController::class, 'antrian_farmasi']);
Route::get('project/antrian_dokter', [\App\Http\Controllers\ProjectController::class, 'antrian_dokter']);
Route::get('project/ambil_antrian', [\App\Http\Controllers\ProjectController::class, 'ambil_antrian']);
Route::get('project/uang', [\App\Http\Controllers\ProjectController::class, 'uang']);
Route::get('project/general_concent', [\App\Http\Controllers\ProjectController::class, 'general_concent']);
