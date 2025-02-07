<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AntrianController;
use App\Http\Controllers\ValidateController;
use App\Http\Controllers\PasienController;
use App\Http\Controllers\WebRegistrationController;

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
/* Route::get('antrianperiksa/monitor', [AntrianController::class, 'monitor']); */
Route::get('antrianperiksa/monitor_baru', [AntrianController::class, 'monitor_baru']);
Route::get('fingerprint/register', [AntrianController::class, 'register']);
Route::get('antrianperiksa/monitor/convert_sound_to_array', [AntrianController::class, 'convertSoundToArray']);
Route::get('/antrianperiksa/monitor/convert_sound_to_array/mobile', [AntrianController::class, 'convertSoundToArrayMobile']);

Route::get('validasi/antigen/{id}', [ValidateController::class, 'antigen']);
Route::get('validasi/tanda_tangan_staf/{id}', [ValidateController::class, 'tandaTanganStaf']);
Route::get('validasi/antibodi/{id}', [ValidateController::class, 'antibodi']);
Route::get('validasi/surat_sakit/{id}', [ValidateController::class, 'surat_sakit']);
Route::get('antrianperiksa/fail', [AntrianController::class, 'fail']);
Route::get('antrianperiksa/{id}', [AntrianController::class, 'antri']);
Route::get('eksklusi/{id}', [PasienController::class, 'eksklusi']);
Route::get('antrianperiksa/monitor/getDataBaru/{panggil_pasien}', [AntrianController::class, 'updateJumlahAntrianBaru']);
Route::get('antrians/get/qrcode/{id}', [AntrianController::class, 'getQr']);

Route::get('daftar_online', [WebRegistrationController::class, 'daftar_online']);
Route::post('daftar_online', [WebRegistrationController::class, 'daftar_online_post']);
Route::get('daftar_online/{no_telp}', [WebRegistrationController::class, 'daftar_online_by_phone']);
Route::get('daftar_online_by_phone/view/refresh', [WebRegistrationController::class, 'view_refresh']);
Route::post('daftar_online_by_phone/submit/tipe_konsultasi', [WebRegistrationController::class, 'submit_tipe_konsultasi']);
Route::post('daftar_online_by_phone/submit/pembayaran', [WebRegistrationController::class, 'submit_pembayaran']);
Route::post('daftar_online_by_phone/submit/nomor_asuransi_bpjs', [WebRegistrationController::class, 'nomor_asuransi_bpjs']);
Route::post('daftar_online_by_phone/submit/nama', [WebRegistrationController::class, 'nama']);
Route::post('daftar_online_by_phone/submit/tanggal_lahir', [WebRegistrationController::class, 'tanggal_lahir']);
Route::post('daftar_online_by_phone/submit/alamat', [WebRegistrationController::class, 'alamat']);
Route::post('daftar_online_by_phone/submit/staf', [WebRegistrationController::class, 'staf']);
Route::post('/daftar_online_by_phone/submit/pasien', [WebRegistrationController::class, 'pasien']);
Route::post('/daftar_online_by_phone/submit/lanjutkan', [WebRegistrationController::class, 'lanjutkan']);
Route::post('/daftar_online_by_phone/submit/ulangi', [WebRegistrationController::class, 'ulangi']);
Route::post('/daftar_online_by_phone/submit/validasi_bpjs', [WebRegistrationController::class, 'validasi_bpjs']);
Route::post('/daftar_online_by_phone/submit/batalkan', [WebRegistrationController::class, 'batalkan']);
Route::post('/daftar_online_by_phone/submit/daftar_lagi', [WebRegistrationController::class, 'daftar_lagi']);
Route::post('/daftar_online_by_phone/submit/hapus_antrian', [WebRegistrationController::class, 'hapus_antrian']);






Route::get('project/antrian_farmasi', [\App\Http\Controllers\ProjectController::class, 'antrian_farmasi']);
Route::get('project/antrian_dokter', [\App\Http\Controllers\ProjectController::class, 'antrian_dokter']);
Route::get('project/ambil_antrian', [\App\Http\Controllers\ProjectController::class, 'ambil_antrian']);
Route::get('project/uang', [\App\Http\Controllers\ProjectController::class, 'uang']);
Route::get('project/general_concent', [\App\Http\Controllers\ProjectController::class, 'general_concent']);
