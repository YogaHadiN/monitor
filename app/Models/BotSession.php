<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSession extends Model
{
    protected $table = 'bot_sessions';

    protected $guarded = [];

    protected $casts = [
        'collected_data'             => 'array',
        'is_complete'                => 'boolean',
        'requires_special_handling'  => 'boolean',
        'last_activity_at'           => 'datetime',
    ];

    public function getData(string $key, $default = null)
    {
        $data = $this->collected_data ?? [];
        return $data[$key] ?? $default;
    }

    public function setData(string $key, $value): void
    {
        $data = $this->collected_data ?? [];
        $data[$key] = $value;
        $this->collected_data = $data;
    }
}
