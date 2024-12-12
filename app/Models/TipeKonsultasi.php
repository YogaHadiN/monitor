<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;
use Carbon\Carbon;

class TipeKonsultasi extends Model
{
    use HasFactory;
    public function getSisaAntrianAttribute(){

        $tipe_konsultasi_id = $this->id;
        $start_of_day       = Carbon::now()->startOfDay()->format("Y-m-d H:i:s");
        $end_of_day         = Carbon::now()->endOfDay()->format("Y-m-d H:i:s");

        $query  = "SELECT ";
        $query .= "count(ant.id) as jumlah ";
        $query .= "FROM antrians ant ";
        $query .= "WHERE tenant_id=". session()->get('tenant_id') . " ";
        $query .= "AND ant.tipe_konsultasi_id = $tipe_konsultasi_id " ;
        $query .= "AND ant.created_at between '$start_of_day' and '$end_of_day' " ;
        $query .= "AND ant.tipe_konsultasi_id = $tipe_konsultasi_id " ;
        $query .= "AND ("; 
        $query .= "antriable_type = 'App\\\Models\\\Antrian' or ";
        $query .= "antriable_type = 'App\\\Models\\\AntrianPoli' or "; 
        $query .= "antriable_type = 'App\\\Models\\\AntrianPeriksa' "; 
        $query .= ") "; 
        $query .= "GROUP BY ant.ruangan_id " ;
        $query .= "ORDER BY count(ant.id) ASC;" ;
        $data = DB::select($query);

        return count( $data )? (int) $data[0]->jumlah :0 ;
    }

    public function getWaktuTungguMenitAttribute(){
        return $this->sisa_antrian * 3;
    }
    
    public function poli_bpjs(){
        return $this->belongsTo(PoliBpjs::class);
    }
    public function ruangan(){
        return $this->belongsTo(Ruangan::class);
    }
}
