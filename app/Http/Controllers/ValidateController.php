<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Periksa;
use DB;
use Auth;
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
        $query .= "tn.name as nama_klinik, ";
        $query .= "ps.nama as nama, ";
        $query .= "px.tanggal as tanggal, ";
        $query .= "ss.tanggal_mulai as tanggal_mulai,";
        $query .= "ss.hari as hari ";
        $query .= "FROM surat_sakits as ss ";
        $query .= "JOIN periksas as px on px.id = ss.periksa_id ";
        $query .= "JOIN pasiens as ps on ps.id = px.pasien_id ";
        $query .= "JOIN tenants as tn on tn.id = px.tenant_id ";
        $query .= "WHERE periksa_id = '{$id}'";
        $data = DB::select($query);
        $nama          = '';
        $nama_klinik   = '';
        $tanggal_mulai = '';
        $hari          = '';
        $tanggal       = '';
        if (count($data)) {
            $nama          = $data[0]->nama;
            $nama_klinik          = $data[0]->nama_klinik;
            $tanggal_mulai = Carbon::parse($data[0]->tanggal_mulai);
            $hari          = $data[0]->hari;
            $tanggal       = Carbon::parse($data[0]->tanggal);
        }
        return view('validasi.surat_sakit', compact(
            'data',
            'nama',
            'nama_klinik',
            'tanggal_mulai',
            'hari',
            'tanggal'
        ));
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
    }
    
    
}
