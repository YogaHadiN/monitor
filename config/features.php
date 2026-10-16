<?php

/**
 * Feature flag centralized. Mirror atika config/features.php —
 * kedua codebase share DB `jatielok`, jadi flag harus konsisten
 * dgn env var yg sama di kedua prod server (.env FEATURES_POOL_ANTRIAN).
 *
 * pool_antrian_enabled = true  → model pool, nomor per tipe konsultasi.
 * pool_antrian_enabled = false → legacy (default).
 */
return [
    'pool_antrian_enabled' => env('FEATURES_POOL_ANTRIAN', false),
];
