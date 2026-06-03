<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mirror dari atika — tabel `sunat_chat_sessions` ada di shared DB
 * jatielok. Monitor pakai untuk:
 *   - upsert session saat pesan inbound mengandung sunat/khitan
 *   - cek session.followup_status saat ada reply → trigger handoff email
 */
class SunatChatSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'awaiting_human' => 'boolean',
        'reply_emailed'  => 'boolean',
        'opt_out'        => 'boolean',
    ];
}
