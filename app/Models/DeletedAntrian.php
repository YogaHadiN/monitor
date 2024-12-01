<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant; 

class DeletedAntrian extends Model
{
    use BelongsToTenant,HasFactory;
    protected $guarded = [];
    public function deleting_antrian(){
        return $this->belongsTo(Antrian::class, 'deleting_antrian_id');
    }
}
