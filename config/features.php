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

    // SunatBot hybrid mode (dr. Yoga 2026-10-16):
    // true  → orchestrator route ke harga/booking specialist via delegate_*
    //         tools. Specialist mode get prompt FOCUSED (strip non-relevant
    //         section). Prompt size per call turun ~35%.
    // false → monolith (current behavior, full 55K prompt tiap call).
    // Rollback: set FEATURES_SUNATBOT_HYBRID=false di .env + reload fpm.
    'sunatbot_hybrid' => env('FEATURES_SUNATBOT_HYBRID', false),
];
