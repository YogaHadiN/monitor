<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotSession extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'collected_data'     => 'array',
        'escalated_to_human' => 'boolean',
        'last_activity_at'   => 'datetime',
    ];

    public static function activeFor(string $noTelp): ?self
    {
        return static::query()
            ->where('no_telp', $noTelp)
            ->where('escalated_to_human', false)
            ->where('current_step', '!=', 'done')
            ->orderByDesc('id')
            ->first();
    }

    public function setData(string $key, $value): void
    {
        $data = $this->collected_data ?? [];
        $data[$key] = $value;
        $this->collected_data = $data;
    }

    public function getData(string $key, $default = null)
    {
        $data = $this->collected_data ?? [];
        return $data[$key] ?? $default;
    }
}
