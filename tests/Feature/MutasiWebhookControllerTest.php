<?php

namespace Tests\Feature;

use App\Jobs\ParseMutasiEmail;
use App\Models\MutasiInboundEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MutasiWebhookControllerTest extends TestCase
{
    private const SECRET = 'unit-test-secret-do-not-use-in-prod';

    protected function setUp(): void
    {
        parent::setUp();

        // Isolasi: jangan sentuh MySQL hidup (phpunit.xml monitor belum
        // diset ke sqlite). Pakai sqlite in-memory khusus untuk test ini
        // dan jadikan default selama test berjalan.
        config(['database.connections.sqlite_mutasi_test' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);
        DB::purge('sqlite_mutasi_test');
        DB::setDefaultConnection('sqlite_mutasi_test');

        config([
            'mutasi.webhook_secret'         => self::SECRET,
            'mutasi.allowed_sender_domains' => ['kopra.mandiri.co.id'],
            'mutasi.s3_disk'                => 's3',
            'mutasi.queue.connection'       => 'redis',
            'mutasi.queue.name'             => 'mutasi',
        ]);

        Schema::create('mutasi_inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255);
            $table->unique('message_id');
            $table->string('source', 255);
            $table->dateTime('received_at');
            $table->string('s3_bucket', 255);
            $table->string('s3_key', 512);
            $table->string('status', 32)->default('received');
            $table->json('verdicts');
            $table->dateTime('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->bigInteger('tenant_id')->default(1);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('mutasi_inbound_emails');
        DB::purge('sqlite_mutasi_test');
        parent::tearDown();
    }

    public function test_valid_signature_creates_record_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = $this->validPayload();
        [$body, $headers] = $this->signed($payload);

        $response = $this->postRaw('/api/webhooks/mutasi', $body, $headers);

        $response->assertStatus(200)->assertJsonFragment(['status' => 'queued']);

        $this->assertDatabaseHas('mutasi_inbound_emails', [
            'message_id' => $payload['messageId'],
            'source'     => $payload['source'],
            's3_bucket'  => $payload['s3']['bucket'],
            's3_key'     => $payload['s3']['key'],
            'status'     => 'received',
        ]);
        $this->assertSame(1, MutasiInboundEmail::count());

        $emailId = MutasiInboundEmail::first()->id;
        Queue::assertPushed(ParseMutasiEmail::class, fn ($job) => $job->emailId === $emailId);
        Queue::assertPushed(ParseMutasiEmail::class, 1);
    }

    public function test_duplicate_message_id_is_idempotent_and_does_not_dispatch_again(): void
    {
        Queue::fake();

        $payload = $this->validPayload();
        [$body, $headers] = $this->signed($payload);

        $this->postRaw('/api/webhooks/mutasi', $body, $headers)->assertStatus(200);
        $second = $this->postRaw('/api/webhooks/mutasi', $body, $headers);

        $second->assertStatus(200)->assertJsonFragment(['status' => 'duplicate']);
        $this->assertSame(1, MutasiInboundEmail::where('message_id', $payload['messageId'])->count());
        Queue::assertPushed(ParseMutasiEmail::class, 1);
    }

    public function test_bad_signature_returns_401_and_does_not_create_record(): void
    {
        Queue::fake();

        $payload = $this->validPayload();
        $body    = json_encode($payload);
        $headers = [
            'CONTENT_TYPE'     => 'application/json',
            'HTTP_X_SIGNATURE' => 'sha256=' . str_repeat('0', 64),
        ];

        $response = $this->postRaw('/api/webhooks/mutasi', $body, $headers);

        $response->assertStatus(401);
        $this->assertSame(0, MutasiInboundEmail::count());
        Queue::assertNothingPushed();
    }

    public function test_failed_verdict_returns_422_and_does_not_dispatch(): void
    {
        Queue::fake();

        $payload                      = $this->validPayload();
        $payload['verdicts']['spam'] = 'FAIL';
        [$body, $headers]            = $this->signed($payload);

        $response = $this->postRaw('/api/webhooks/mutasi', $body, $headers);

        $response->assertStatus(422);
        $this->assertSame(0, MutasiInboundEmail::count());
        Queue::assertNothingPushed();
    }

    public function test_sender_domain_not_in_whitelist_returns_422(): void
    {
        Queue::fake();

        $payload           = $this->validPayload();
        $payload['source'] = 'phisher@evil.example.com';
        [$body, $headers]  = $this->signed($payload);

        $response = $this->postRaw('/api/webhooks/mutasi', $body, $headers);

        $response->assertStatus(422);
        $this->assertSame(0, MutasiInboundEmail::count());
        Queue::assertNothingPushed();
    }

    public function test_missing_signature_header_returns_401(): void
    {
        Queue::fake();

        $body = json_encode($this->validPayload());

        $response = $this->postRaw('/api/webhooks/mutasi', $body, [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, MutasiInboundEmail::count());
        Queue::assertNothingPushed();
    }

    private function validPayload(): array
    {
        return [
            'messageId'   => 'ses-msg-test-' . bin2hex(random_bytes(6)),
            'source'      => 'no-reply@kopra.mandiri.co.id',
            'destination' => ['mutasi@inbound.klinikjatielok.com'],
            'receivedAt'  => '2026-06-01T05:00:00+07:00',
            's3'          => [
                'bucket' => 'klinik-mutasi-inbound',
                'key'    => 'mutasi/ses-msg-test-001',
            ],
            'verdicts'    => [
                'spf'   => 'PASS',
                'dkim'  => 'PASS',
                'dmarc' => 'PASS',
                'spam'  => 'PASS',
                'virus' => 'PASS',
            ],
        ];
    }

    private function signed(array $payload): array
    {
        $body = json_encode($payload);
        $sig  = 'sha256=' . hash_hmac('sha256', $body, self::SECRET);
        return [
            $body,
            [
                'CONTENT_TYPE'     => 'application/json',
                'HTTP_X_SIGNATURE' => $sig,
                'HTTP_ACCEPT'      => 'application/json',
            ],
        ];
    }

    /**
     * Kirim request POST dengan RAW body apa adanya — penting karena
     * middleware menghitung HMAC dari $request->getContent().
     */
    private function postRaw(string $uri, string $body, array $headers = [])
    {
        return $this->call('POST', $uri, [], [], [], $headers, $body);
    }
}
