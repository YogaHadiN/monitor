<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Periksa;
use DB;
use Carbon\Carbon;
use Crypt;

class ValidateController extends Controller
{
    public function antigen($id){
        return $this->templateRapid(true, $id);
    }
    public function antibodi($id){
        return $this->templateRapid(false, $id);
    }
    public function surat_sakit($id){
        $query  = "SELECT ";
        $query .= "ps.nama as nama, ";
        $query .= "px.tanggal as tanggal, ";
        $query .= "ss.tanggal_mulai as tanggal_mulai,";
        $query .= "ss.hari as hari ";
        $query .= "FROM surat_sakits as ss ";
        $query .= "JOIN periksas as px on px.id = ss.periksa_id ";
        $query .= "JOIN pasiens as ps on ps.id = px.pasien_id ";
        $query .= "WHERE periksa_id = '{$id}'";
        $data = DB::select($query);
        $nama          = '';
        $tanggal_mulai = '';
        $hari          = '';
        $tanggal       = '';
        if (count($data)) {
            $nama          = $data[0]->nama;
            $tanggal_mulai = Carbon::parse($data[0]->tanggal_mulai);
            $hari          = $data[0]->hari;
            $tanggal       = Carbon::parse($data[0]->tanggal);
        }
        return view('validasi.surat_sakit', compact(
            'data',
            'nama',
            'tanggal_mulai',
            'hari',
            'tanggal'
        ));
        return 'Halaman ini menyatakan bahwa benar ' . ucwords($nama) . ' Mengajukan Izin Sakit mulai tanggal ' . $tanggal_mulai . ' selama ' . $hari. ' Hari di Klinik Jati Elok pada tanggal ' . $tanggal;
    }
    public function templateRapid($antigen, $id){
        $query  = "SELECT ";
        $query .= "psn.nama as nama, ";
        $query .= "prx.tanggal as tanggal ";
        $query .= "FROM transaksi_periksas as trx ";
        $query .= "JOIN periksas as prx on prx.id = trx.periksa_id ";
        $query .= "JOIN pasiens as psn on psn.id = prx.pasien_id ";
        $query .= "WHERE periksa_id = '{$id}' ";
        $query .= "AND jenis_tarif_id = ";
        if ($antigen) {
            $query .="'404';"; //rapid test antigen; //rapid test antigen
        } else {
            $query .="'403';"; //rapid test antibodi
        }
        $data = DB::select($query);

        return view('validasi.rapid', compact(
            'data',
            'antigen'
        ));
        /* if ( count($data) ) { */
        /*     $nama = $data[0]->nama; */
        /*     $tanggal = $data[0]->tanggal; */
        /*     $pemeriksaan = $antigen ? 'Rapid Antigen COVID 19' : 'Rapid Antibodi COVID 19'; */
        /*     return 'Halaman ini menyatakan bahwa benar ' . ucwords($nama) . ' telah melakukan pemeriksaan ' . $pemeriksaan . ' di Klinik Jati Elok pada tanggal ' . $tanggal; */
        /* } else { */
        /*     return 'Data tidak ditemukan Mohon hubungi 0215977529 untuk verifikasi lebih lanjut'; */
        /* } */
    }
    
    
}
