<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Log;
use App\Models\Tenant;

class BpjsApiController extends Controller
{
    public $stamp;
    public $post;
    public $url;
    public $tglDaftar;
    public $noKartu;
    public $when;
    public $base_url;
    public function __construct(){
        $this->when = 'today';
        $this->base_url = "https://apijkn.bpjs-kesehatan.go.id/pcare-rest/";
    }

    public function pencarianDiagnosa(){
        $this->apiBpjsGetRequest('diagnosa/A0/1/1');
    }
    public function pencarianDiagnosaTidakDitemukan(){
        $this->apiBpjsGetRequest('diagnosa/XYZ/1/1');
    }
    public function pencarianDokter(){
        $this->apiBpjsGetRequest('dokter/1/1');
    }
    public function pencarianReferensiKesadaran(){
        $this->apiBpjsGetRequest('kesadaran');
    }
    public function pencarianReferensiPoli(){
        $this->apiBpjsGetRequest('poli/fktp/1/17');
    }
    public function pencarianReferensiProviderRayonisasi(){
        $this->apiBpjsGetRequest('provider/1/1');
    }
    public function pencarianReferensiStatusPulang($rawat_inap = 'false'){
         $this->apiBpjsGetRequest('statuspulang/rawatInap/' . $rawat_inap) ;
    }
    public function pencarianReferensiSpesialis(){
         $this->apiBpjsGetRequest('spesialis') ;
    }
    public function pencarianReferensiSubspesialis(){
         $this->apiBpjsGetRequest('spesialis/ANA/subspesialis') ;
    }
    public function pencarianReferensiSarana(){
         $this->apiBpjsGetRequest('spesialis/sarana') ;
    }
    public function pencarianReferensiKhusus(){
         $this->apiBpjsGetRequest('spesialis/khusus') ;
    }
    public function pencarianFaskesRujukanSubspesialis(){
         $this->apiBpjsGetRequest('spesialis/rujuk/subspesialis/26/sarana/1/tglEstRujuk/' . date('d-m-Y')) ;
    }
    public function pencarianNoKartuValid($no_kartu){
         return $this->apiBpjsGetRequest('peserta/noka/'.$no_kartu, true);
    }
    public function pencarianNoKartuLebihDari13Digit(){
         $this->apiBpjsGetRequest('peserta/noka/'.$this->noKartu() . '1');
    }
    public function pencarianNoKartuKurangDari13Digit(){
         $this->apiBpjsGetRequest('peserta/noka/'.substr_replace($this->noKartu() ,"", -1) );
    }
    public function pencarianNoKartuTidakDitemukan(){
         $this->apiBpjsGetRequest('peserta/noka/1231231231234' );
    }
    public function pencarianNikValid(){
         $this->apiBpjsGetRequest('peserta/nik/2171031603729012');
    }
    public function pencarianNikLebihDari16Digit(){
         $this->apiBpjsGetRequest('peserta/nik/21710316037290121');
    }
    public function pencarianNikKurangDari16Digit(){
         $this->apiBpjsGetRequest('peserta/nik/21710316037290');
    }
    public function pencarianNikNonNumerik(){
         $this->apiBpjsGetRequest('peserta/nik/217103160372a012');
    }
    public function pencarianNikTidakDitemukan(){
         $this->apiBpjsGetRequest('peserta/nik/1230001231231231');
    }
    public function validasipendaftaranLebihDariTanggalHariIni(){
        $data = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider = $data['kdProviderPst']['kdProvider'];
        $noKartu = $data['noKartu'];
        $this->tglDaftar = date('d-m-Y', strtotime('+1 day'));
        $this->post = '{
                          "kdProviderPeserta": "'.$kdProvider.'",
                          "tglDaftar": "'.$this->tglDaftar.'",
                          "noKartu": "'.$noKartu.'",
                          "kdPoli": "001",
                          "keluhan": null,
                          "kunjSakit": true,
                          "sistole": 0,
                          "diastole": 0,
                          "beratBadan": 0,
                          "tinggiBadan": 0,
                          "respRate": 0,
                          "lingkarPerut": 0,
                          "heartRate": 0,
                          "rujukBalik": 0,
                          "kdTkp": "10"
                      }';
        $this->apiBpjsPostRequest('pendaftaran');
    }
    public function validasipendaftaranNoKartuHarusNumerik(){
        $data = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider = $data['kdProviderPst']['kdProvider'];
        $noKartu = removeLastCharacter( $data['noKartu'] ) . 'a';
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $this->post = '{
                          "kdProviderPeserta": "'.$kdProvider.'",
                          "tglDaftar": "'.$this->tglDaftar.'",
                          "noKartu": "'.$noKartu.'",
                          "kdPoli": "001",
                          "keluhan": null,
                          "kunjSakit": true,
                          "sistole": 0,
                          "diastole": 0,
                          "beratBadan": 0,
                          "tinggiBadan": 0,
                          "respRate": 0,
                          "lingkarPerut": 0,
                          "heartRate": 0,
                          "rujukBalik": 0,
                          "kdTkp": "10"
                      }';
        $this->apiBpjsPostRequest('pendaftaran');
    }
    public function validasipendaftaranNoKartuTidakDitemukan(){
        $data = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider = $data['kdProviderPst']['kdProvider'];
        $noKartu = removeLastCharacter( $data['noKartu'] ) . '0';
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $this->post = '{
                          "kdProviderPeserta": "'.$kdProvider.'",
                          "tglDaftar": "'.$this->tglDaftar.'",
                          "noKartu": "'.$noKartu.'",
                          "kdPoli": "001",
                          "keluhan": null,
                          "kunjSakit": true,
                          "sistole": 0,
                          "diastole": 0,
                          "beratBadan": 0,
                          "tinggiBadan": 0,
                          "respRate": 0,
                          "lingkarPerut": 0,
                          "heartRate": 0,
                          "rujukBalik": 0,
                          "kdTkp": "10"
                      }';
        $this->apiBpjsPostRequest('pendaftaran');
    }
    public function validasiKodePoliTidakDitemukan(){
        $data = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider = $data['kdProviderPst']['kdProvider'];
        $noKartu = $data['noKartu'] ;
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $kdPoli = '036';
        $this->post = '{
                          "kdProviderPeserta": "'.$kdProvider.'",
                          "tglDaftar": "'.$this->tglDaftar.'",
                          "noKartu": "'.$noKartu.'",
                          "kdPoli": "'.$kdPoli.'",
                          "keluhan": null,
                          "kunjSakit": true,
                          "sistole": 0,
                          "diastole": 0,
                          "beratBadan": 0,
                          "tinggiBadan": 0,
                          "respRate": 0,
                          "lingkarPerut": 0,
                          "heartRate": 0,
                          "rujukBalik": 0,
                          "kdTkp": "10"
                      }';
        $this->apiBpjsPostRequest('pendaftaran');
    }
    public function validasiKunjSakitMenggunakanKodePoli020(){
        $data            = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider      = $data['kdProviderPst']['kdProvider'];
        $noKartu         = $data['noKartu'] ;
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $kdPoli          = '020';
        $this->post = '{
                          "kdProviderPeserta": "'.$kdProvider.'",
                          "tglDaftar": "'.$this->tglDaftar.'",
                          "noKartu": "'.$noKartu.'",
                          "kdPoli": "'.$kdPoli.'",
                          "keluhan": null,
                          "kunjSakit": true,
                          "sistole": 0,
                          "diastole": 0,
                          "beratBadan": 0,
                          "tinggiBadan": 0,
                          "respRate": 0,
                          "lingkarPerut": 0,
                          "heartRate": 0,
                          "rujukBalik": 0,
                          "kdTkp": "10"
                      }';
        $this->apiBpjsPostRequest('pendaftaran');
    }

    public function validasiHapusPendaftaranStatusPasienSudahDilayani(){
        $data            = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider      = $data['kdProviderPst']['kdProvider'];
        $noKartu         = $data['noKartu'] ;
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $kdPoli          = '001';
        $this->post = '{
                          "kdProviderPeserta": "'.$kdProvider.'",
                          "tglDaftar": "'.$this->tglDaftar.'",
                          "noKartu": "'.$noKartu.'",
                          "kdPoli": "'.$kdPoli.'",
                          "keluhan": null,
                          "kunjSakit": true,
                          "sistole": 0,
                          "diastole": 0,
                          "beratBadan": 0,
                          "tinggiBadan": 0,
                          "respRate": 0,
                          "lingkarPerut": 0,
                          "heartRate": 0,
                          "rujukBalik": 0,
                          "kdTkp": "10"
                      }';
        $data   = $this->apiBpjsPostRequest('pendaftaran', true);
        $noUrut = $data['message'];
        $kdDokter = $this->returnKdDokter();
        $this->post = '{
              "noKunjungan": null,
              "noKartu": "'.$noKartu.'",
              "tglDaftar": "'.$this->tglDaftar.'",
              "kdPoli": "'.$kdPoli.'",
              "keluhan": "demam",
              "kdSadar": "01",
              "sistole": 60,
              "diastole": 80,
              "beratBadan": 75,
              "tinggiBadan": 180,
              "respRate": 30,
              "heartRate": 30,
              "lingkarPerut": 25,
              "kdStatusPulang": "3",
              "tglPulang": "'.$this->tglDaftar.'",
              "kdDokter": "'.$kdDokter.'",
              "kdDiag1": "K00.1",
              "kdDiag2": null,
              "kdDiag3": null,
              "kdPoliRujukInternal": null,
              "rujukLanjut": {
                "tglEstRujuk":null,
                "kdppk": null,
                "subSpesialis": null,
                "khusus": null
              },
              "kdTacc": 0,
              "alasanTacc": null}';
        $data = $this->apiBpjsPostRequest('kunjungan', true);
        $this->apiBpjsDeleteRequest('pendaftaran/peserta/' . $noKartu. '/tglDaftar/'.$this->tglDaftar.'/noUrut/'.$noUrut.'/kdPoli/'. $kdPoli);
    }

    public function pendaftaranKunjunganSehatValid(){
        $data            = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider      = $data['kdProviderPst']['kdProvider'];
        $noKartu         = $data['noKartu'] ;
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $kdPoli          = '021';
        $this->post = '{ "kdProviderPeserta": "'.$kdProvider.'", "tglDaftar": "'. $this->tglDaftar .'", "noKartu": "'.$noKartu.'", "kdPoli": "'.$kdPoli.'", "keluhan": null, "kunjSakit": false, "sistole": 0, "diastole": 0, "beratBadan": 0, "tinggiBadan": 0, "respRate": 0, "lingkarPerut": 0, "heartRate": 0, "rujukBalik": 0, "kdTkp": "10" }';
        $this->apiBpjsPostRequest('pendaftaran');

    }

    public function hapusPendaftaranKunjunganSehat(){
        $data            = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider      = $data['kdProviderPst']['kdProvider'];
        $noKartu         = $data['noKartu'] ;
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $kdPoli          = '021';
        $this->post = '{ "kdProviderPeserta": "'.$kdProvider.'", "tglDaftar": "'. $this->tglDaftar .'", "noKartu": "'.$noKartu.'", "kdPoli": "'.$kdPoli.'", "keluhan": null, "kunjSakit": false, "sistole": 0, "diastole": 0, "beratBadan": 0, "tinggiBadan": 0, "respRate": 0, "lingkarPerut": 0, "heartRate": 0, "rujukBalik": 0, "kdTkp": "10" }';
        $response = $this->apiBpjsPostRequest('pendaftaran', true);
        $noUrut = $response['message'];
        $this->apiBpjsDeleteRequest('pendaftaran/peserta/'.$noKartu.'/tglDaftar/'.$this->tglDaftar.'/noUrut/'.$noUrut.'/kdPoli/' . $kdPoli);
    }

    public function hapusPendaftaranKunjunganSakit(){
        $data            = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $kdProvider      = $data['kdProviderPst']['kdProvider'];
        $noKartu         = $data['noKartu'] ;
        $this->tglDaftar = date('d-m-Y', strtotime('today'));
        $kdPoli          = '001';
        $this->post = '{ "kdProviderPeserta": "'.$kdProvider.'", "tglDaftar": "'. $this->tglDaftar .'", "noKartu": "'.$noKartu.'", "kdPoli": "'.$kdPoli.'", "keluhan": null, "kunjSakit": true, "sistole": 0, "diastole": 0, "beratBadan": 0, "tinggiBadan": 0, "respRate": 0, "lingkarPerut": 0, "heartRate": 0, "rujukBalik": 0, "kdTkp": "10" }';
        $response = $this->apiBpjsPostRequest('pendaftaran', true);
        $noUrut = $response['message'];
        $this->apiBpjsDeleteRequest('pendaftaran/peserta/'.$noKartu.'/tglDaftar/'.$this->tglDaftar.'/noUrut/'.$noUrut.'/kdPoli/' . $kdPoli);
    }

    public function entryDataPelayananStatusPulangSembuh(){
        $response      = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $this->tglDaftar     = date("d-m-Y", strtotime( $this->when ));
        $kdPoli        = '001';
        $this->post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$this->tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "20"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', true);
        $kdDokter = $this->returnKdDokter();
        $this->post = '{
            "noKunjungan": null,
            "noKartu": "'.$noKartu.'",
            "tglDaftar": "'.$this->tglDaftar.'",
            "kdPoli": "'.$kdPoli.'",
            "keluhan": "demam",
            "kdSadar": "01",
            "sistole": 60,
            "diastole": 80,
            "beratBadan": 75,
            "tinggiBadan": 180,
            "respRate": 30,
            "heartRate": 30,
            "lingkarPerut": 25,
            "kdStatusPulang": "0",
            "tglPulang": "'.$this->tglDaftar.'",
            "kdDokter": "'.$kdDokter.'",
            "kdDiag1": "K00.1",
            "kdDiag2": null,
            "kdDiag3": null,
            "kdPoliRujukInternal": null,
            "rujukLanjut": {
            "tglEstRujuk": null,
                "kdppk": null,
                "subSpesialis": {
                "kdSubSpesialis1":null,
                    "kdSarana": null
            },
                "khusus": null
            },
            "kdTacc": -1,
            "alasanTacc": null
        }';

        $this->apiBpjsPostRequest('kunjungan');
    }
    public function entryPelayananDenganStatusPulangRujukLanjutKhusus(){
        $response        = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $noKartu         = $response['noKartu'];
        $kdProviderPst   = $response['kdProviderPst']['kdProvider'];
        $this->tglDaftar = date("d-m-Y", strtotime( $this->when ));
        $tommorow        = Carbon::createFromFormat( 'd-m-Y', $this->tglDaftar )->addDay()->format('d-m-Y');
        $kdPoli        = '001'; //poli umum
        $this->post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$this->tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "20"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', true);
        $kdDokter = $this->returnKdDokter();
        $this->post = '{
                "noKunjungan": null,
                "noKartu": "'.$noKartu.'",
                "tglDaftar": "'. $this->tglDaftar .'",
                "kdPoli": null,
                "keluhan": "keluhan",
                "kdSadar": "01",
                "sistole": 0,
                "diastole": 0,
                "beratBadan": 0,
                "tinggiBadan": 0,
                "respRate": 0,
                "heartRate": 0,
                "lingkarPerut": 36,
                "kdStatusPulang": "4",
                "tglPulang": "'. $this->tglDaftar .'",
                "kdDokter": "'. $kdDokter .'",
                "kdDiag1": "A01.0",
                "kdDiag2": null,
                "kdDiag3": null,
                "kdPoliRujukInternal": null,
                "rujukLanjut": {
                "tglEstRujuk":"' . $tommorow . '",
                "kdppk": "0221R014",
                "subSpesialis": null,
                "khusus": {
                "kdKhusus": "HDL",
                "kdSubSpesialis": null,
                "catatan": "peserta sudah biasa hemodialisa"
                }
                },
                "kdTacc": 0,
                "alasanTacc": null
        }';
        $this->apiBpjsPostRequest('kunjungan');
    }

    public function entryDataPelayananStatusPulangPulangPaksa(){

        $response      = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $this->tglDaftar     = date("d-m-Y", strtotime('-13 days'));
        $kdPoli        = '001';
        $this->post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$this->tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "20"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', true);
        $kdDokter = $this->returnKdDokter();
        $this->post = '{
            "noKunjungan": null,
            "noKartu": "'.$noKartu.'",
            "tglDaftar": "'.$this->tglDaftar.'",
            "kdPoli": "'.$kdPoli.'",
            "keluhan": "demam",
            "kdSadar": "01",
            "sistole": 60,
            "diastole": 80,
            "beratBadan": 75,
            "tinggiBadan": 180,
            "respRate": 30,
            "heartRate": 30,
            "lingkarPerut": 25,
            "kdStatusPulang": "2",
            "tglPulang": "'.$this->tglDaftar.'",
            "kdDokter": "'.$kdDokter.'",
            "kdDiag1": "K00.1",
            "kdDiag2": null,
            "kdDiag3": null,
            "kdPoliRujukInternal": null,
            "rujukLanjut": {
            "tglEstRujuk": null,
                "kdppk": null,
                "subSpesialis": {
                "kdSubSpesialis1":null,
                    "kdSarana": null
            },
                "khusus": null
            },
            "kdTacc": -1,
            "alasanTacc": null
        }';
        $this->apiBpjsPostRequest('kunjungan');
    }

    public function deletePendaftaran($noKartu,$noUrut)
    {
        $yesterday = date('d-m-Y', strtotime('today'));
        $kdPoli = '001';
        $this->apiBpjsDeleteRequest('pendaftaran/peserta/'.$noKartu.'/tglDaftar/'.$yesterday.'/noUrut/'.$noUrut.'/kdPoli/'.$kdPoli);
    }


    public function entryPelayananDenganStatusPulangRujukInternal(){

        $response      = $this->apiBpjsGetRequest('peserta/' . $this->noKartu(), true);
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $this->tglDaftar     = date("d-m-Y", strtotime('today'));
        $kdPoli        = '001';
        $this->post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$this->tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', true);
        $kdDokter = $this->returnKdDokter();
        $this->post = '{
            "noKunjungan": null,
            "noKartu": "'.$noKartu.'",
            "tglDaftar": "'.$this->tglDaftar.'",
            "kdPoli": "'.$kdPoli.'",
            "keluhan": "demam",
            "kdSadar": "01",
            "sistole": 60,
            "diastole": 80,
            "beratBadan": 75,
            "tinggiBadan": 180,
            "respRate": 30,
            "heartRate": 30,
            "lingkarPerut": 25,
            "kdStatusPulang": "6",
            "tglPulang": "'.$this->tglDaftar.'",
            "kdDokter": "'.$kdDokter.'",
            "kdDiag1": "K00.1",
            "kdDiag2": null,
            "kdDiag3": null,
            "kdPoliRujukInternal": "002",
            "rujukLanjut": {
            "tglEstRujuk": null,
                "kdppk": null,
                "subSpesialis": {
                "kdSubSpesialis1":null,
                    "kdSarana": null
            },
                "khusus": null
            },
            "kdTacc": -1,
            "alasanTacc": null
        }';

        $this->apiBpjsPostRequest('kunjungan');
    }
    public function postKunjungan(){
        $noKartu         = $this->noKartu();
        $response        = $this->apiBpjsGetRequest('peserta/' . $noKartu, true);
        $this->noKartu         = $response['noKartu'];
        $this->kdProviderPst   = $response['kdProviderPst']['kdProvider'];
        $this->tglDaftar = date("d-m-Y", strtotime('-12 days'));
        $this->kdPoli          = '001';
        $this->post      = '{
                  "kdProviderPeserta": "'.$this->kdProviderPst.'",
                  "tglDaftar": "'.$this->tglDaftar.'",
                  "noKartu": "'.$this->noKartu.'",
                  "kdPoli": "'.$this->kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', true);
        $this->kdDokter = $this->returnKdDokter();
        $this->post = '{
              "noKunjungan": null,
              "noKartu": "'.$this->noKartu.'",
              "tglDaftar": "'.$this->tglDaftar.'",
              "kdPoli": "'.$this->kdPoli.'",
              "keluhan": "demam",
              "kdSadar": "01",
              "sistole": 60,
              "diastole": 80,
              "beratBadan": 75,
              "tinggiBadan": 180,
              "respRate": 30,
              "heartRate": 30,
              "lingkarPerut": 25,
              "kdStatusPulang": "3",
              "tglPulang": "'.$this->tglDaftar.'",
              "kdDokter": "'.$this->kdDokter.'",
              "kdDiag1": "K00.1",
              "kdDiag2": null,
              "kdDiag3": null,
              "kdPoliRujukInternal": null,
              "rujukLanjut": {
                "tglEstRujuk":null,
                "kdppk": null,
                "subSpesialis": null,
                "khusus": null
              },
              "kdTacc": 0,
              "alasanTacc": null}';
        $data = $this->apiBpjsPostRequest('kunjungan', true);
        if (!is_null( $data )) {
            $this->noKunjungan = $data[0]['message'];
        }
    }
    public function editKunjungan(){
        if (!is_null( $this->noKunjungan )) {
            $this->post = ' {
                                "noKunjungan": "'.$this->noKunjungan.'",
                                "noKartu": "'. $this->noKartu .'",
                                "keluhan": "keluhan",
                                "kdSadar": "03",
                                "sistole": 90,
                                "diastole": 90,
                                "beratBadan": 50,
                                "tinggiBadan": 170,
                                "respRate": 90,
                                "heartRate": 90,
                                "lingkarPerut": 36,
                                "kdStatusPulang": "4",
                                "tglPulang": "'. $this->tglDaftar .'",
                                "kdDokter": "'  . $this->kdDokter . '",
                                "kdDiag1": "B54",
                                "kdDiag2": null,
                                "kdDiag3": null,
                                "kdPoliRujukInternal": null,
                                "rujukLanjut": {
                                  "tglEstRujuk": "27-03-2022",
                                  "kdppk": "0112R016",
                                  "subSpesialis": null,
                                  "khusus": {
                                      "kdKhusus": "HDL",
                                      "kdSubSpesialis": null,
                                      "catatan": "peserta sudah biasa hemodialisa"
                                  }
                                },
                                "kdTacc": 1,
                                "alasanTacc": "< 3 Hari"
                            }';

            $this->apiBpjsPutRequest('kunjungan');
        } else {
            dd( 'no Kunjungan belum dibuat' );
        }
    }



    public function getClubProlanis($noKegiatan){
        $data = $this->apiBpjsGetRequest('kelompok/club/' . $noKegiatan);
        return $this->returnDecryptedResponse( $data );
    }

    public function tambahKegiatanKelompok(){
        $tglDaftar = date('d-m-Y');
        $post = '{ "eduId": null, "clubId": 17496, "tglPelayanan": "'.$tglDaftar.'", "kdKegiatan": "01", "kdKelompok": "03", "materi": "materi", "pembicara": "pembicara", "lokasi": "lokasi", "keterangan": "keterangan", "biaya": 20000 }';

        dd( $this->apiBpjsPostRequest('kelompok/kegiatan', $post) );
    }


    public function deleteAllPendaftaran()
    {
        foreach ($this->getPendaftaran() as $pendaftaran) {
            $noKartu = $pendaftaran['peserta']['noKartu'];
            $noUrut = $pendaftaran['noUrut'];
            $this->deletePendaftaran($noKartu, $noUrut);
        }
    }
    public function getPendaftaran()
    {
        $this->apiBpjsGetRequest('pendaftaran/tglDaftar/'. date('d-m-Y', strtotime( $this->when )) .'/0/10');
    }

    public function returnKdDokter()
    {
        $response      = $this->apiBpjsGetRequest('dokter/1/1', true);
        return $response['list'][0]['kdDokter'];
    }
    public function returnDecryptedResponse($data)
    {
        $data = json_decode($data, true);
        dd( $data );
        if (!is_null( $data )) {
            if (
                isset( $data['metaData'] )
            ) {
                if (
                    $data['metaData']['code'] >= 200 &&
                    $data['metaData']['code'] <= 299
                ) {
                    return [
                        'code'     => $data['metaData']['code'],
                        'response' => json_decode( $this->decompress( $this->stringDecrypt(  $data['response']  ) ), true )
                    ];
                } else {
                    return [
                        'code'     => $data['metaData']['code'],
                        'response' => $data['metaData']['message']
                    ];
                }
            } else if (
                isset( $data['metadata'] )
            ) {
                if (
                    $data['metadata']['code'] >= 200 &&
                    $data['metadata']['code'] <= 299
                ) {
                    return [
                        'code'     => $data['metadata']['code'],
                        'response' => json_decode( $this->decompress( $this->stringDecrypt(  $data['response']  ) ), true )
                    ];
                } else {
                    return [
                        'code'     => $data['metadata']['code'],
                        'response' => $data['metadata']['message']
                    ];
                }
            }
        } else {
            return [
                'code'     => 503,
                'response' => 'Service Unavailable'
            ];
        }
    }
    public function postKunjunganDoubleEntry($noKartu){
        $tglDaftar = date('d-m-Y', strtotime('yesterday'));
        $kdPoli = '001';
        $kdDokter = $this->returnKdDokter();
        $post = '{
            "noKunjungan": null,
            "noKartu": "'.$noKartu.'",
            "tglDaftar": "'.$tglDaftar.'",
            "kdPoli": "'.$kdPoli.'",
            "keluhan": "demam",
            "kdSadar": "01",
            "sistole": 60,
            "diastole": 80,
            "beratBadan": 75,
            "tinggiBadan": 180,
            "respRate": 30,
            "heartRate": 30,
            "lingkarPerut": 25,
            "kdStatusPulang": "4",
            "tglPulang": "'.$tglDaftar.'",
            "kdDokter": "'.$kdDokter.'",
            "kdDiag1": "K00.1",
            "kdDiag2": null,
            "kdDiag3": null,
            "kdPoliRujukInternal": null,
            "rujukLanjut": {
            "tglEstRujuk": "'.$tglDaftar.'",
                "kdppk": "0221R014",
                "subSpesialis": {
                "kdSubSpesialis1": "42",
                    "kdSarana": null
            },
                "khusus": null
            },
            "kdTacc": -1,
            "alasanTacc": null
        }';

        $data = $this->apiBpjsPostRequest('kunjungan', $post);
        /* $data = $this->returnDecryptedResponse($data); */
        dd( $data );
    }
    public function postKunjunganValidasiTanggalGagal($noKartu){
        $response      = $this->returnDecryptedResponse( $this->apiBpjsGetRequest('peserta/' . $noKartu) );
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $tglDaftar     = date("d-m-Y", strtotime('today'));
        $kdPoli        = '001';
        $post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', $post);
        $response = $this->returnDecryptedResponse( $data );
        $tglDaftar     = date("d-m-Y", strtotime('tommorow'));
        $kdDokter = $this->returnKdDokter();
        $post = '{
            "noKunjungan": null,
            "noKartu": "'.$noKartu.'",
            "tglDaftar": "'.$tglDaftar.'",
            "kdPoli": "'.$kdPoli.'",
            "keluhan": "demam",
            "kdSadar": "01",
            "sistole": 60,
            "diastole": 80,
            "beratBadan": 75,
            "tinggiBadan": 180,
            "respRate": 30,
            "heartRate": 30,
            "lingkarPerut": 25,
            "kdStatusPulang": "4",
            "tglPulang": "'.$tglDaftar.'",
            "kdDokter": "'.$kdDokter.'",
            "kdDiag1": "K00.1",
            "kdDiag2": null,
            "kdDiag3": null,
            "kdPoliRujukInternal": null,
            "rujukLanjut": {
            "tglEstRujuk": "'.$tglDaftar.'",
                "kdppk": "0221R014",
                "subSpesialis": {
                "kdSubSpesialis1": "42",
                    "kdSarana": null
            },
                "khusus": null
            },
            "kdTacc": -1,
            "alasanTacc": null
        }';
        $data = $this->apiBpjsPostRequest('kunjungan', $post);
        dd( $data );
    }
    public function postKunjunganRujukanSubspesialis($noKartu, $when){

        $response      = $this->returnDecryptedResponse( $this->apiBpjsGetRequest('peserta/' . $noKartu) );
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $tglDaftar     = date("d-m-Y", strtotime( $when ));
        $kdPoli        = '001';
        $post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', $post);
        $response = $this->returnDecryptedResponse( $data );
        $kdDokter = $this->returnKdDokter();
        $post = '{
            "noKunjungan": null,
            "noKartu": "'.$noKartu.'",
            "tglDaftar": "'.$tglDaftar.'",
            "kdPoli": "'.$kdPoli.'",
            "keluhan": "demam",
            "kdSadar": "01",
            "sistole": 60,
            "diastole": 80,
            "beratBadan": 75,
            "tinggiBadan": 180,
            "respRate": 30,
            "heartRate": 30,
            "lingkarPerut": 25,
            "kdStatusPulang": "4",
            "tglPulang": "'.$tglDaftar.'",
            "kdDokter": "'.$kdDokter.'",
            "kdDiag1": "K00.1",
            "kdDiag2": null,
            "kdDiag3": null,
            "kdPoliRujukInternal": null,
            "rujukLanjut": {
            "tglEstRujuk": "'.$tglDaftar.'",
                "kdppk": "0221R014",
                "subSpesialis": {
                "kdSubSpesialis1": "42",
                    "kdSarana": null
            },
                "khusus": null
            },
            "kdTacc": -1,
            "alasanTacc": null
        }';
        $data = $this->returnDecryptedResponse($this->apiBpjsPostRequest('kunjungan', $post));
        dd( $data );
    }
    public function postKunjunganMeninggal($noKartu, $when){
        $response      = $this->returnDecryptedResponse( $this->apiBpjsGetRequest('peserta/' . $noKartu) );
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $tglDaftar     = date("d-m-Y", strtotime($when));
        $kdPoli        = '001';
        $post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', $post);
        $kdDokter = $this->returnKdDokter();
        $post = '{
              "noKunjungan": null,
              "noKartu": "'.$noKartu.'",
              "tglDaftar": "'.$tglDaftar.'",
              "kdPoli": "'.$kdPoli.'",
              "keluhan": "demam",
              "kdSadar": "01",
              "sistole": 60,
              "diastole": 80,
              "beratBadan": 75,
              "tinggiBadan": 180,
              "respRate": 30,
              "heartRate": 30,
              "lingkarPerut": 25,
              "kdStatusPulang": "1",
              "tglPulang": "'.$tglDaftar.'",
              "kdDokter": "'.$kdDokter.'",
              "kdDiag1": "K00.1",
              "kdDiag2": null,
              "kdDiag3": null,
              "kdPoliRujukInternal": null,
              "rujukLanjut": {
                "tglEstRujuk":null,
                "kdppk": null,
                "subSpesialis": null,
                "khusus": null
              },
              "kdTacc": 0,
              "alasanTacc": null}';
        $data = $this->apiBpjsPostRequest('kunjungan', $post);
        dd( $this->returnDecryptedResponse(  $data  ) );
    }
    public function postKunjunganPulangPaksa($noKartu, $when){
        $response      = $this->returnDecryptedResponse( $this->apiBpjsGetRequest('peserta/' . $noKartu) );
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $tglDaftar     = date("d-m-Y", strtotime($when));
        $kdPoli        = '001';
        $post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', $post);
        $kdDokter = $this->returnKdDokter();
        $post = '{
              "noKunjungan": null,
              "noKartu": "'.$noKartu.'",
              "tglDaftar": "'.$tglDaftar.'",
              "kdPoli": "'.$kdPoli.'",
              "keluhan": "demam",
              "kdSadar": "01",
              "sistole": 60,
              "diastole": 80,
              "beratBadan": 75,
              "tinggiBadan": 180,
              "respRate": 30,
              "heartRate": 30,
              "lingkarPerut": 25,
              "kdStatusPulang": "2",
              "tglPulang": "'.$tglDaftar.'",
              "kdDokter": "'.$kdDokter.'",
              "kdDiag1": "K00.1",
              "kdDiag2": null,
              "kdDiag3": null,
              "kdPoliRujukInternal": null,
              "rujukLanjut": {
                "tglEstRujuk":null,
                "kdppk": null,
                "subSpesialis": null,
                "khusus": null
              },
              "kdTacc": 0,
              "alasanTacc": null}';
        $data = $this->apiBpjsPostRequest('kunjungan', $post);
        dd( $this->returnDecryptedResponse(  $data  ) );
    }
    public function postKunjunganSembuh($noKartu, $when){
        $response      = $this->returnDecryptedResponse( $this->apiBpjsGetRequest('peserta/' . $noKartu) );
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $tglDaftar     = date("d-m-Y", strtotime($when));
        $kdPoli        = '001';
        $post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', $post);
        $kdDokter = $this->returnKdDokter();
        $post = '{
              "noKunjungan": null,
              "noKartu": "'.$noKartu.'",
              "tglDaftar": "'.$tglDaftar.'",
              "kdPoli": "'.$kdPoli.'",
              "keluhan": "demam",
              "kdSadar": "01",
              "sistole": 60,
              "diastole": 80,
              "beratBadan": 75,
              "tinggiBadan": 180,
              "respRate": 30,
              "heartRate": 30,
              "lingkarPerut": 25,
              "kdStatusPulang": "0",
              "tglPulang": "'.$tglDaftar.'",
              "kdDokter": "'.$kdDokter.'",
              "kdDiag1": "K00.1",
              "kdDiag2": null,
              "kdDiag3": null,
              "kdPoliRujukInternal": null,
              "rujukLanjut": {
                "tglEstRujuk":null,
                "kdppk": null,
                "subSpesialis": null,
                "khusus": null
              },
              "kdTacc": 0,
              "alasanTacc": null}';
        $data = $this->apiBpjsPostRequest('kunjungan', $post);
        dd( $this->returnDecryptedResponse(  $data  ) );
    }

    public function postKunjunganStatusPulangLain2($noKartu, $when){
        $response      = $this->returnDecryptedResponse( $this->apiBpjsGetRequest('peserta/' . $noKartu) );
        $noKartu       = $response['noKartu'];
        $kdProviderPst = $response['kdProviderPst']['kdProvider'];
        $tglDaftar     = date("d-m-Y", strtotime($when));
        $kdPoli        = '001';
        $post = '{
                  "kdProviderPeserta": "'.$kdProviderPst.'",
                  "tglDaftar": "'.$tglDaftar.'",
                  "noKartu": "'.$noKartu.'",
                  "kdPoli": "'.$kdPoli.'",
                  "keluhan": null,
                  "kunjSakit": true,
                  "sistole": 0,
                  "diastole": 0,
                  "beratBadan": 0,
                  "tinggiBadan": 0,
                  "respRate": 0,
                  "lingkarPerut": 0,
                  "heartRate": 0,
                  "rujukBalik": 0,
                  "kdTkp": "10"
                }';
        $data     = $this->apiBpjsPostRequest('pendaftaran', $post);
        $kdDokter = $this->returnKdDokter();
        $post = '{
              "noKunjungan": null,
              "noKartu": "'.$noKartu.'",
              "tglDaftar": "'.$tglDaftar.'",
              "kdPoli": "'.$kdPoli.'",
              "keluhan": "demam",
              "kdSadar": "01",
              "sistole": 60,
              "diastole": 80,
              "beratBadan": 75,
              "tinggiBadan": 180,
              "respRate": 30,
              "heartRate": 30,
              "lingkarPerut": 25,
              "kdStatusPulang": "9",
              "tglPulang": "'.$tglDaftar.'",
              "kdDokter": "'.$kdDokter.'",
              "kdDiag1": "K00.1",
              "kdDiag2": null,
              "kdDiag3": null,
              "kdPoliRujukInternal": null,
              "rujukLanjut": {
                "tglEstRujuk":null,
                "kdppk": null,
                "subSpesialis": null,
                "khusus": null
              },
              "kdTacc": 0,
              "alasanTacc": null}';
        $data = $this->apiBpjsPostRequest('kunjungan', $post);
        dd( $this->returnDecryptedResponse(  $data  ) );
    }

    /**
     * undocumented function
     *
     * @return void
     */
    public function deleteKunjungan($noKunjungan)
    {
        return $this->apiBpjsDeleteRequest('kunjungan/' .$noKunjungan);
    }
    public function riwayatKunjunganPeserta($noKartu)
    {
        return $this->returnDecryptedResponse( $this->apiBpjsGetRequest('kunjungan/peserta/' .$noKartu) );
    }


    /**
     * undocumented function
     *
     * @return void
     */
    public function formatResponse($data)
    {
        $data = json_decode($data, 'true');
        dd([
            'response' => $this->decompress( $this->stringDecrypt(  $data['response']  ) ),
            'metadata' => [
                'message' => $data['metadata']['message'],
                'code' => $data['metadata']['code']
            ]
        ]);
    }


    public function apiBpjsGetRequest($parameter, $returnResponse = false)
    {
        $tenant = Tenant::find(1);
		$uri        = $this->base_url . $parameter; //url web service bpjs;

		$this->url  = $uri;
		$consID     = $tenant->bpjs_consid;
		$secretKey  = $tenant->bpjs_secret_key; //secretKey anda
		$pcareUname = $tenant->pcare_uname; //username pcare
		$pcarePWD   = $tenant->pcare_password;//password pcare anda
		$user_key   = $tenant->bpjs_user_key; //password pcare anda
		$kdAplikasi = $tenant->bpjs_kd_aplikasi; //kode aplikasi

        $this->stamp = time();
        $this->request_type = "GET";
        $stamp = $this->stamp;
		$data                 = $consID.'&'.$stamp;

		$signature            = hash_hmac('sha256', $data, $secretKey, true);
		$encodedSignature     = base64_encode($signature);
		$encodedAuthorization = base64_encode($pcareUname.':'.$pcarePWD.':'.$kdAplikasi);

		$headers = array(
					"Accept: application/json",
					"X-cons-id:".$consID,
					"X-timestamp: ".$stamp,
					"X-signature: ".$encodedSignature,
                    "X-authorization: Basic " .$encodedAuthorization ,
                    "user_key: ".$user_key ,

				);

		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$data = curl_exec($ch);
		curl_close($ch);
        return $this->returnDecryptedResponse($data, $returnResponse);
	}
    public function apiBpjsPostRequest($parameter, $returnResponse = false)
    {
		$uri        = "https://apijkn-dev.bpjs-kesehatan.go.id/pcare-rest-dev/" . $parameter; //url web service bpjs;
        $this->url  = $uri;
		$consID     = env('BPJS_CONSID'); //customer ID anda
		$secretKey  = env('BPJS_SECRET_KEY'); //secretKey anda
		$pcareUname = env('BPJS_PCARE_UNAME'); //username pcare
		$pcarePWD   = env('BPJS_PCARE_PWD'); //password pcare anda
		$user_key   = env('BPJS_USER_KEY'); //password pcare anda
		$kdAplikasi = env('BPJS_KD_APLIKASI'); //kode aplikasi

        $this->stamp = time();
		$stamp                = $this->stamp;
		$data                 = $consID.'&'.$stamp;
		$signature            = hash_hmac('sha256', $data, $secretKey, true);
		$encodedSignature     = base64_encode($signature);
		$encodedAuthorization = base64_encode($pcareUname.':'.$pcarePWD.':'.$kdAplikasi);

		$headers = array(
					"Accept: application/json",
					"X-cons-id:".$consID,
					"X-timestamp: ".$stamp,
					"X-signature: ".$encodedSignature,
                    "X-authorization: Basic " .$encodedAuthorization ,
                    "user_key: ".$user_key ,
				);
        $this->request_type = "POST";
		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->post);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$data = curl_exec($ch);
		curl_close($ch);
        return $this->returnDecryptedResponse($data, $returnResponse);
	}
    public function apiBpjsPutRequest($parameter, $returnResponse = false)
    {
		$uri        = "https://apijkn-dev.bpjs-kesehatan.go.id/pcare-rest-dev/" . $parameter; //url web service bpjs;
        $this->url  = $uri;
		$consID     = env('BPJS_CONSID'); //customer ID anda
		$secretKey  = env('BPJS_SECRET_KEY'); //secretKey anda
		$pcareUname = env('BPJS_PCARE_UNAME'); //username pcare
		$pcarePWD   = env('BPJS_PCARE_PWD'); //password pcare anda
		$user_key   = env('BPJS_USER_KEY'); //password pcare anda
		$kdAplikasi = env('BPJS_KD_APLIKASI'); //kode aplikasi

        $this->stamp = time();
		$stamp                = $this->stamp;
		$data                 = $consID.'&'.$stamp;
		$signature            = hash_hmac('sha256', $data, $secretKey, true);
		$encodedSignature     = base64_encode($signature);
		$encodedAuthorization = base64_encode($pcareUname.':'.$pcarePWD.':'.$kdAplikasi);

		$headers = array(
					"Accept: application/json",
					"X-cons-id:".$consID,
					"X-timestamp: ".$stamp,
					"X-signature: ".$encodedSignature,
                    "X-authorization: Basic " .$encodedAuthorization ,
                    "user_key: ".$user_key ,
				);


		$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$data = curl_exec($ch);
		curl_close($ch);
        return $this->returnDecryptedResponse($data, $returnResponse);
	}
    public function apiBpjsDeleteRequest($parameter, $returnResponse = false)
    {
		$uri        = "https://apijkn-dev.bpjs-kesehatan.go.id/pcare-rest-dev/" . $parameter; //url web service bpjs;
        $this->url  = $uri;
		$consID     = env('BPJS_CONSID'); //customer ID anda
		$secretKey  = env('BPJS_SECRET_KEY'); //secretKey anda
		$pcareUname = env('BPJS_PCARE_UNAME'); //username pcare
		$pcarePWD   = env('BPJS_PCARE_PWD'); //password pcare anda
		$user_key   = env('BPJS_USER_KEY'); //password pcare anda
		$kdAplikasi = env('BPJS_KD_APLIKASI'); //kode aplikasi

        $this->stamp = time();
		$stamp                = $this->stamp;
		$data                 = $consID.'&'.$stamp;
		$signature            = hash_hmac('sha256', $data, $secretKey, true);
		$encodedSignature     = base64_encode($signature);
		$encodedAuthorization = base64_encode($pcareUname.':'.$pcarePWD.':'.$kdAplikasi);

		$headers = array(
					"Accept: application/json",
					"X-cons-id:".$consID,
					"X-timestamp: ".$stamp,
					"X-signature: ".$encodedSignature,
                    "X-authorization: Basic " .$encodedAuthorization ,
                    "user_key: ".$user_key ,
				);

        $this->request_type = "DELETE";
		$ch = curl_init($uri);

		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->request_type);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$data = curl_exec($ch);
		curl_close($ch);

        return $this->returnDecryptedResponse($data, $returnResponse);
	}

    public function stringDecrypt($string){
        $consID 	= env('BPJS_CONSID'); //customer ID anda
        $secretKey 	= env('BPJS_SECRET_KEY'); //secretKey anda
        $pcareUname = env('BPJS_PCARE_UNAME'); //username pcare
        $pcarePWD 	= env('BPJS_PCARE_PWD'); //password pcare anda
        $kdAplikasi	= env('BPJS_KD_APLIKASI'); //kode aplikasi

        $stamp = $this->stamp;
        $key = $consID . $secretKey . $stamp;

        $encrypt_method = 'AES-256-CBC';
        // hash
        $key_hash = hex2bin(hash('sha256', $key));

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);

        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);

        return $output;
    }
        // function lzstring decompress
        // download libraries lzstring : https://github.com/nullpunkt/lz-string-php
    public function decompress($string){
        return \LZCompressor\LZString::decompressFromEncodedURIComponent($string);
    }

    private function noKartu(){

        $items = [
            '0002039807452',
            '0002039850527',
            '0002040108311',
            '0002040298097',
            '0002066556925',
            '0002040811896',
            '0002040826577',
            '0002053741037',
            '0002047538171',
            '0002053780661',
            '0002041756907',
            '0002041929628',
            '0002041975596',
            '0002053781166',
        ];

        return $items[array_rand($items)];
    }
}
