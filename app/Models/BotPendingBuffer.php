<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotPendingBuffer extends Model
{
    protected $table = 'bot_pending_buffers';

    protected $guarded = [];

    protected $casts = [
        'messages'         => 'array',
        'version'          => 'integer',
        'last_received_at' => 'datetime',
        'processed_at'     => 'datetime',
    ];
}
