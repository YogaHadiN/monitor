<?php

return [
    // Phone numbers (E.164 without "+") that may complete the
    // registration flow even when the clinic is closed (outside
    // tenants.jam_buka / jam_tutup, or outside the 7-21 window of
    // registrasiAntrianOnline). Used for QA so we can test the
    // pendaftaran end-to-end after hours. Override via env as a
    // comma-separated list: CLINIC_AFTER_HOURS_WHITELIST_PHONES.
    'after_hours_whitelist_phones' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('CLINIC_AFTER_HOURS_WHITELIST_PHONES', '6281381912803')
    )))),
];
