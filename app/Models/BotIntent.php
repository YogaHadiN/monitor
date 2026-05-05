<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotIntent extends Model
{
    protected $table = 'bot_intents';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function keywordList(): array
    {
        return $this->splitCsv($this->keywords);
    }

    public function mediaList(): array
    {
        return $this->splitCsv($this->media);
    }

    private function splitCsv(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }
}
