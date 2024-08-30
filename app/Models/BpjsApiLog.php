<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant; 

class BpjsApiLog extends Model
{
    use BelongsToTenant,HasFactory;
    protected $guarded = [];
    public static function boot(){
        parent::boot();
        self::creating(function($bpjs_api_log){
            $bpjs_api_log->url = url()->full();
            $bpjs_api_log->request_type = \Request::method();
        });
    }
    
}
