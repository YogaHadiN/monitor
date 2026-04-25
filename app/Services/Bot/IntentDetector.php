<?php

namespace App\Services\Bot;

class IntentDetector
{
    public function detect(string $message): ?string
    {
        $msg = strtolower($message);

        if (preg_match('/\b(sunat|khitan|sirkumsisi|circumcision)\b/iu', $msg)) {
            return 'sunat';
        }

        return null;
    }
}
