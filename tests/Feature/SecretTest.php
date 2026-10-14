<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecretTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // phpunit.xml points REDIS_DB at a database used only by the tests.
        // Refuse to flush anything else, e.g. when a cached config overrides it.
        $this->assertSame('15', (string) config('database.redis.default.database'));
        Redis::flushdb();
    }

    public function test_pages_send_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'self';", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_error_pages_send_security_headers(): void
    {
        $response = $this->get('/not-a-secret-id');

        $response->assertNotFound();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_store_saves_the_ciphertext_with_its_lifetime(): void
    {
        $response = $this->postJson('/save', ['ciphertext' => 'abc_DEF-123', 'expires' => 3600]);

        $response->assertCreated();
        $id = $response->json('id');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{24}$/', $id);

        $stored = Redis::get('secret:'.$id);
        $this->assertNotSame('abc_DEF-123', $stored);
        $this->assertSame('abc_DEF-123', Crypt::decryptString($stored));

        $ttl = Redis::ttl('secret:'.$id);
        $this->assertGreaterThan(3590, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);
    }

    #[DataProvider('invalidSecrets')]
    public function test_store_rejects_invalid_input(array $payload, string $field): void
    {
        $this->postJson('/save', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame([], Redis::keys('*'));
    }

    public static function invalidSecrets(): array
    {
        return [
            'missing ciphertext' => [['expires' => 3600], 'ciphertext'],
            'plain text instead of ciphertext' => [['ciphertext' => 'my password', 'expires' => 3600], 'ciphertext'],
            'ciphertext too long' => [['ciphertext' => str_repeat('a', 80_039), 'expires' => 3600], 'ciphertext'],
            'missing lifetime' => [['ciphertext' => 'abc'], 'expires'],
            'negative lifetime' => [['ciphertext' => 'abc', 'expires' => -5], 'expires'],
            'zero lifetime' => [['ciphertext' => 'abc', 'expires' => 0], 'expires'],
            'lifetime over a week' => [['ciphertext' => 'abc', 'expires' => 604801], 'expires'],
            'lifetime not offered' => [['ciphertext' => 'abc', 'expires' => 60], 'expires'],
        ];
    }

    public function test_the_longest_allowed_ciphertext_is_accepted(): void
    {
        $this->postJson('/save', ['ciphertext' => str_repeat('a', 80_038), 'expires' => 3600])
            ->assertCreated();
    }

    public function test_a_secret_can_only_be_retrieved_once(): void
    {
        $id = $this->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])->json('id');

        $this->postJson('/get', ['id' => $id])
            ->assertOk()
            ->assertExactJson(['ciphertext' => 'abc']);

        $this->postJson('/get', ['id' => $id])
            ->assertNotFound()
            ->assertJsonPath('message', 'Your secret cannot be found. Please ask the sender to try again.');

        $this->assertSame(0, Redis::exists('secret:'.$id));
    }

    public function test_get_rejects_ids_that_could_reach_other_redis_keys(): void
    {
        Redis::set('one-time-secret-cache-abc', 'not a secret');

        $this->postJson('/get', ['id' => 'one-time-secret-cache-abc'])->assertUnprocessable();
        $this->postJson('/get', ['id' => 'secret:'.Str::random(24)])->assertUnprocessable();
        $this->postJson('/get', ['id' => Str::random(20)])->assertUnprocessable();

        $this->assertSame('not a secret', Redis::get('one-time-secret-cache-abc'));
    }

    public function test_a_secret_that_cannot_be_decrypted_is_deleted_and_reported_missing(): void
    {
        $id = Str::random(24);
        Redis::set('secret:'.$id, 'encrypted under some other APP_KEY');

        $this->postJson('/get', ['id' => $id])->assertNotFound();

        $this->assertSame(0, Redis::exists('secret:'.$id));
    }

    public function test_viewing_the_link_does_not_touch_the_secret(): void
    {
        $id = Str::random(24);
        Redis::set('secret:'.$id, Crypt::encryptString('abc'));

        $this->get('/'.$id)
            ->assertOk()
            ->assertSee('Reveal secret');

        $this->assertSame(1, Redis::exists('secret:'.$id));
    }

    public function test_the_page_does_not_contain_the_id(): void
    {
        // A third-party stylesheet could read it out of a DOM attribute.
        $id = Str::random(24);

        $this->get('/'.$id)->assertOk()->assertDontSee($id);
    }

    public function test_only_secret_ids_are_routed(): void
    {
        $this->get('/'.Str::random(20))->assertNotFound();
        $this->get('/'.Str::random(22))->assertNotFound();
        $this->get('/'.Str::random(25))->assertNotFound();
        $this->get('/'.Str::random(19).'-')->assertNotFound();
        $this->get('/storage/anything')->assertNotFound();
        $this->put('/storage/anything')->assertNotFound();
    }

    public function test_retrieving_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/get', ['id' => Str::random(24)])->assertNotFound();
        }

        $this->postJson('/get', ['id' => Str::random(24)])->assertTooManyRequests();
    }

    public function test_storing_is_rate_limited_per_minute(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])->assertCreated();
        }

        $this->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])->assertTooManyRequests();
    }

    public function test_storing_is_rate_limited_per_day(): void
    {
        for ($minute = 0; $minute < 15; $minute++) {
            $this->travel(1)->minutes();

            for ($i = 0; $i < 20; $i++) {
                $this->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])->assertCreated();
            }
        }

        $this->travel(1)->minutes();
        $this->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])->assertTooManyRequests();
    }

    public function test_ipv6_clients_share_a_limit_across_their_slash_64(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::'.dechex($i + 1)])
                ->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])
                ->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2:ffff::1'])
            ->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])
            ->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])
            ->postJson('/save', ['ciphertext' => 'abc', 'expires' => 3600])
            ->assertCreated();
    }
}
