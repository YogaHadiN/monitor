<?php

namespace App\Http\Controllers;

use App\Jobs\ParseMutasiEmail;
use App\Models\MutasiInboundEmail;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Webhook ingress untuk notifikasi email mutasi bank yang sudah dilanding
 * Lambda (AWS SES inbound) ke S3. Payload kecil (hanya pointer + metadata);
 * raw email diambil belakangan oleh ParseMutasiEmail dari S3.
 *
 * Signature HMAC sudah diverifikasi middleware `verify.mutasi.signature`
 * sebelum sampai di sini.
 *
 * Tabel `mutasi_inbound_emails` di-host di DB jatielok (di-migrate dari
 * sisi atika). Monitor menulis ke tabel yang sama via shared DB.
 */
class MutasiWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = (array) $request->json()->all();

        $validator = Validator::make($data, [
            'messageId'      => 'required|string|max:255',
            'source'         => 'required|email|max:255',
            'destination'    => 'required|array|min:1',
            'destination.*'  => 'required|string|max:255',
            'receivedAt'     => 'required|string|max:64',
            's3'             => 'required|array',
            's3.bucket'      => 'required|string|max:255',
            's3.key'         => 'required|string|max:512',
            'verdicts'       => 'required|array',
            'verdicts.spf'   => 'required|string|max:32',
            'verdicts.dkim'  => 'required|string|max:32',
            'verdicts.dmarc' => 'required|string|max:32',
            'verdicts.spam'  => 'required|string|max:32',
            'verdicts.virus' => 'required|string|max:32',
        ]);

        if ($validator->fails()) {
            Log::warning('MutasiWebhook: payload invalid', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'message' => 'Invalid payload',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Semua verdict SES harus PASS — kalau ada yang tidak, JANGAN
        // dispatch parsing. Email gagal verifikasi tidak boleh masuk ke
        // pipeline keuangan.
        foreach ($data['verdicts'] as $kind => $value) {
            if (strtoupper((string) $value) !== 'PASS') {
                Log::warning('MutasiWebhook: verdict gagal', [
                    'messageId' => $data['messageId'],
                    'verdict'   => $kind,
                    'value'     => $value,
                ]);
                return response()->json([
                    'message' => "Verdict {$kind} tidak PASS",
                ], 422);
            }
        }

        // Whitelist domain pengirim (mis. Kopra Mandiri). Bila kosong,
        // tolak semua — opt-in, bukan opt-out.
        $domain  = strtolower(substr(strrchr((string) $data['source'], '@') ?: '@', 1));
        $allowed = array_map('strtolower', (array) config('mutasi.allowed_sender_domains', []));
        if ($domain === '' || !in_array($domain, $allowed, true)) {
            Log::warning('MutasiWebhook: domain pengirim tidak diizinkan', [
                'messageId' => $data['messageId'],
                'source'    => $data['source'],
                'domain'    => $domain,
            ]);
            return response()->json([
                'message' => 'Sender domain not allowed',
            ], 422);
        }

        // Idempotent insert berbasis UNIQUE(message_id). firstOrCreate
        // mengandalkan constraint DB; bila terjadi race (dua webhook
        // bersamaan), salah satu transaksi akan kalah QueryException
        // duplicate key — tangkap dan lookup record yang sudah ada.
        try {
            $email = MutasiInboundEmail::firstOrCreate(
                ['message_id' => $data['messageId']],
                [
                    'source'      => $data['source'],
                    'received_at' => $data['receivedAt'],
                    's3_bucket'   => $data['s3']['bucket'],
                    's3_key'      => $data['s3']['key'],
                    'status'      => 'received',
                    'verdicts'    => $data['verdicts'],
                    'tenant_id'   => 1,
                ]
            );
        } catch (QueryException $e) {
            // Race: baris dibuat oleh request lain di antara SELECT dan
            // INSERT firstOrCreate. Ambil baris yang menang.
            $email = MutasiInboundEmail::where('message_id', $data['messageId'])->first();
            if ($email === null) {
                Log::error('MutasiWebhook: gagal insert dan tidak ada baris', [
                    'messageId' => $data['messageId'],
                    'error'     => $e->getMessage(),
                ]);
                return response()->json(['message' => 'Internal error'], 500);
            }
        }

        if ($email->wasRecentlyCreated) {
            ParseMutasiEmail::dispatch($email->id);
            Log::info('MutasiWebhook: queued', [
                'id'         => $email->id,
                'message_id' => $email->message_id,
            ]);
            return response()->json([
                'status' => 'queued',
                'id'     => $email->id,
            ], 200);
        }

        // Duplikat — JANGAN dispatch ulang.
        Log::info('MutasiWebhook: duplicate', [
            'id'         => $email->id,
            'message_id' => $email->message_id,
        ]);
        return response()->json([
            'status' => 'duplicate',
            'id'     => $email->id,
        ], 200);
    }
}
