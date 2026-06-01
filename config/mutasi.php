<?php

return [
    // Shared HMAC secret yang dipakai Lambda (AWS SES inbound) untuk
    // menandatangani body request webhook. Tanpa nilai ini middleware
    // VerifyMutasiSignature menolak semua request.
    'webhook_secret' => env('MUTASI_WEBHOOK_SECRET'),

    // Domain pengirim email mutasi yang diizinkan (mis. Kopra Mandiri).
    // Format env: comma-separated, mis. "kopra.mandiri.co.id,notifikasi.bca.co.id".
    'allowed_sender_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MUTASI_ALLOWED_SENDER_DOMAINS', ''))
    ))),

    // Nama disk Storage yang menunjuk ke bucket S3 berisi raw email yang
    // diunggah Lambda. Default 's3' (lihat config/filesystems.php).
    's3_disk' => env('MUTASI_S3_DISK', 's3'),

    // Pengaturan queue untuk job ParseMutasiEmail. Wajib pakai redis +
    // queue terpisah supaya tidak mengganggu queue default lain di EC2.
    'queue' => [
        'connection' => env('MUTASI_QUEUE_CONNECTION', 'redis'),
        'name'       => env('MUTASI_QUEUE_NAME', 'mutasi'),
    ],
];
