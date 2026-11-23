<?php

namespace Loyvo\Tests;

use Loyvo\Exception\AuthenticationError;
use Loyvo\Exception\ConnectionError;
use Loyvo\Exception\LoyvoException;
use Loyvo\Exception\PaymentRequiredError;
use Loyvo\Exception\PermissionDeniedError;
use Loyvo\Exception\RateLimitError;
use Loyvo\Exception\ServerError;
use Loyvo\Exception\ValidationError;
use Loyvo\Http\Response;
use Loyvo\Http\Transport;
use Loyvo\Http\TransportException;
use Loyvo\Loyvo;
use Loyvo\Phone;
use Loyvo\Version;
use PHPUnit\Framework\TestCase;

/** Records requests and answers from a script. */
final class FakeTransport implements Transport
{
    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: mixed, timeout: float}> */
    public $calls = [];
    /** @var array<int, Response|TransportException> */
    private $replies;

    /** @param Response|TransportException ...$replies */
    public function __construct(...$replies)
    {
        $this->replies = $replies;
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): Response
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body === null ? null : json_decode($body, true), 'timeout' => $timeoutSeconds];
        $r = array_shift($this->replies) ?? new Response(200, [], '{}');
        if ($r instanceof TransportException) {
            throw $r;
        }
        return $r;
    }
}

final class ClientTest extends TestCase
{
    /** @var int[] */
    private $slept = [];

    /** @param array<string, mixed> $body @param array<string, string> $headers */
    private static function json(int $status, $body = [], array $headers = []): Response
    {
        return new Response($status, $headers, json_encode($body));
    }

    /** @param array<string, mixed> $opts */
    private function client(FakeTransport $t, array $opts = []): Loyvo
    {
        return new Loyvo($opts + [
            'apiKey' => 'lvo_test_abc',
            'transport' => $t,
            'maxRetries' => 2,
            'sleep' => function (int $ms): void {
                $this->slept[] = $ms;
            },
        ]);
    }

    /** @return LoyvoException */
    private static function catchError(callable $fn)
    {
        try {
            $fn();
        } catch (LoyvoException $e) {
            return $e;
        }
        self::fail('expected a LoyvoException');
    }

    // ------------------------------------------------------------ requests

    public function testSendsKeyUserAgentAndMapsSmsFields(): void
    {
        $t = new FakeTransport(self::json(202, ['campaign_id' => 'cmp_1', 'test' => true]));
        $r = $this->client($t)->sms->send(['from' => 'QUBA', 'to' => ['0771234567'], 'message' => 'Hi', 'scheduleAt' => new \DateTimeImmutable('2026-12-01T10:00:00Z')]);
        $this->assertSame('cmp_1', $r['campaign_id']);
        $c = $t->calls[0];
        $this->assertSame('POST', $c['method']);
        $this->assertSame('https://api.loyvo.lk/v1/sms/send', $c['url']);
        $this->assertSame('Bearer lvo_test_abc', $c['headers']['Authorization']);
        $this->assertStringStartsWith('loyvo-php/', $c['headers']['User-Agent']);
        $this->assertStringStartsWith('loyvo-', $c['headers']['Idempotency-Key']);
        $this->assertSame(['sender_id' => 'QUBA', 'to' => ['0771234567'], 'message' => 'Hi', 'schedule_at' => '2026-12-01T10:00:00+00:00'], $c['body']);
    }

    public function testBulkCampaignPagingAndSenderIds(): void
    {
        $t = new FakeTransport(self::json(202), self::json(200, ['campaign' => [], 'messages' => [], 'pagination' => []]), self::json(200, ['data' => [['mask' => 'QUBA']]]));
        $l = $this->client($t);
        $l->sms->bulk(['from' => 'QUBA', 'message' => 'Hi {{name}}', 'recipients' => [['to' => '0771234567', 'vars' => ['name' => 'Kamal']]], 'name' => 'Promo']);
        $l->campaigns->get('cmp_1', 2, 50);
        $this->assertSame([['mask' => 'QUBA']], $l->senderIds->list());
        $this->assertSame('Promo', $t->calls[0]['body']['name']);
        $this->assertSame([['to' => '0771234567', 'vars' => ['name' => 'Kamal']]], $t->calls[0]['body']['recipients']);
        $this->assertSame('https://api.loyvo.lk/v1/campaigns/cmp_1?page=2&page_size=50', $t->calls[1]['url']);
        $this->assertSame('https://api.loyvo.lk/v1/sender-ids', $t->calls[2]['url']);
    }

    public function testPosCallsUseTheWalletRoutes(): void
    {
        $t = new FakeTransport(self::json(200, ['exists' => true]), self::json(201, ['points_awarded' => 25]), self::json(200, ['redemption_id' => 'r1']), self::json(200));
        $l = $this->client($t, ['apiKey' => 'lvo_live_x']);
        $l->pos->resolve(['barcodeValue' => 'BC1']);
        $l->pos->transaction(['phone' => '0771234567', 'amount' => 2500, 'invoiceId' => 'INV-1']);
        $l->pos->redemptions->initiate(['phone' => '0771234567', 'points' => 100]);
        $l->pos->redemptions->confirm(['phone' => '0771234567', 'redemptionId' => 'r1', 'pin' => 1234]);
        $this->assertSame(
            ['/v1/wallet/pos/customers/resolve', '/v1/wallet/pos/transactions', '/v1/wallet/pos/redemptions/initiate', '/v1/wallet/pos/redemptions/confirm'],
            array_map(function ($c) {
                return parse_url($c['url'], PHP_URL_PATH);
            }, $t->calls)
        );
        $this->assertSame(['barcodeValue' => 'BC1'], $t->calls[0]['body']);
        $this->assertSame(['phone' => '0771234567', 'amount' => 2500, 'invoiceId' => 'INV-1'], $t->calls[1]['body']);
        $this->assertSame(['phone' => '0771234567', 'pointsToRedeem' => 100], $t->calls[2]['body']);
        $this->assertSame(['phone' => '0771234567', 'redemptionId' => 'r1', 'pin' => '1234'], $t->calls[3]['body']);
    }

    public function testPosNeedsACustomer(): void
    {
        $e = self::catchError(function () {
            $this->client(new FakeTransport())->pos->transaction(['amount' => 10]);
        });
        $this->assertSame('missing_customer', $e->errorCode);
    }

    public function testWhoamiBalanceLoyaltyAndPasses(): void
    {
        $t = new FakeTransport(
            self::json(200, ['env' => 'live', 'org' => ['id' => 'org_1', 'name' => 'QUBA']]),
            self::json(200, ['credits' => 10]),
            self::json(200),
            new Response(204, [], ''),
            self::json(200, ['saveUrl' => 'https://pay.google.com/x']),
            new Response(200, ['Content-Type' => 'application/vnd.apple.pkpass'], "PK\x03\x04")
        );
        $l = $this->client($t);
        $this->assertSame('QUBA', $l->whoami()['org']['name']);
        $this->assertSame(10, $l->balance()['credits']);
        $l->loyalty->lookupCustomer('0771234567');
        $l->loyalty->deleteBranch(3);
        $this->assertSame('https://pay.google.com/x', $l->passes->googleSaveUrl('p1'));
        $this->assertSame("PK\x03\x04", $l->passes->applePass('p1'));
        $this->assertSame('https://api.loyvo.lk/v1/key', $t->calls[0]['url']);
        $this->assertSame('https://api.loyvo.lk/v1/balance', $t->calls[1]['url']);
        $this->assertSame('https://api.loyvo.lk/v1/loyalty/pos/customers/lookup?phone=0771234567', $t->calls[2]['url']);
        $this->assertSame('DELETE', $t->calls[3]['method']);
        $this->assertSame('*/*', $t->calls[5]['headers']['Accept']);
    }

    public function testCardTemplates(): void
    {
        $t = new FakeTransport(self::json(200, [['id' => 'tpl_1', 'name' => 'QUBA Rewards', 'type' => 'POINTS', 'status' => 'PUBLISHED']]), self::json(200, ['id' => 'tpl_1']));
        $l = $this->client($t, ['apiKey' => 'lvo_live_x']);
        $this->assertSame('QUBA Rewards', $l->cardTemplates->list()[0]['name']);
        $l->cardTemplates->get('tpl_1');
        $this->assertSame('https://api.loyvo.lk/v1/wallet/card-templates', $t->calls[0]['url']);
        $this->assertSame('https://api.loyvo.lk/v1/wallet/card-templates/tpl_1', $t->calls[1]['url']);
    }

    public function testBaseUrlEnvKeyAndMissingKey(): void
    {
        putenv('LOYVO_API_KEY=lvo_live_env');
        $t = new FakeTransport(self::json(200));
        $l = new Loyvo(['transport' => $t, 'baseUrl' => 'http://localhost:9000/', 'timeout' => 5]);
        putenv('LOYVO_API_KEY');
        $l->balance();
        $this->assertSame('http://localhost:9000/v1/balance', $t->calls[0]['url']);
        $this->assertSame('Bearer lvo_live_env', $t->calls[0]['headers']['Authorization']);
        $this->assertSame(5.0, $t->calls[0]['timeout']);
        $this->assertFalse($l->isTest());
        $e = self::catchError(function () {
            new Loyvo(['transport' => new FakeTransport()]);
        });
        $this->assertSame('missing_api_key', $e->errorCode);
    }

    // ------------------------------------------------------------ errors

    public function testReadsTheSmsErrorShape(): void
    {
        $t = new FakeTransport(self::json(422, ['error' => ['code' => 'validation_failed', 'message' => 'Unknown sender ID', 'field' => 'sender_id', 'request_id' => 'req_1']]));
        $e = self::catchError(function () use ($t) {
            $this->client($t)->sms->send(['from' => 'X', 'to' => '1', 'message' => 'm']);
        });
        $this->assertInstanceOf(ValidationError::class, $e);
        $this->assertSame([422, 'validation_failed', 'sender_id', 'req_1', 'Unknown sender ID'], [$e->status, $e->errorCode, $e->field, $e->requestId, $e->getMessage()]);
    }

    public function testReadsTheWalletErrorShapes(): void
    {
        $t = new FakeTransport(
            self::json(403, ['error' => ['code' => 'test_key_not_supported', 'message' => 'Test keys work with the SMS API only.']]),
            self::json(409, ['error' => "POS isn't set up for this business yet"])
        );
        $l = $this->client($t);
        $e1 = self::catchError(function () use ($l) {
            $l->pos->resolve(['phone' => '0771234567']);
        });
        $this->assertInstanceOf(PermissionDeniedError::class, $e1);
        $this->assertSame('test_key_not_supported', $e1->errorCode);
        $e2 = self::catchError(function () use ($l) {
            $l->pos->resolve(['phone' => '0771234567']);
        });
        $this->assertStringContainsString("POS isn't set up", $e2->getMessage());
        $this->assertSame('conflict', $e2->errorCode);
    }

    public function testReadsTheLoyaltyErrorShapes(): void
    {
        $t = new FakeTransport(self::json(400, ['status' => 'error', 'errors' => ['phone' => ['Enter a valid phone.']]]), self::json(401, ['detail' => 'Invalid token.']));
        $l = $this->client($t, ['apiKey' => 'lvo_live_x']);
        $e1 = self::catchError(function () use ($l) {
            $l->loyalty->resendPass('x');
        });
        $this->assertInstanceOf(ValidationError::class, $e1);
        $this->assertSame(['phone', 'Enter a valid phone.'], [$e1->field, $e1->getMessage()]);
        $e2 = self::catchError(function () use ($l) {
            $l->loyalty->getConfig();
        });
        $this->assertInstanceOf(AuthenticationError::class, $e2);
        $this->assertSame('Invalid token.', $e2->getMessage());
    }

    public function testPaymentRequiredKeepsTheRequestIdHeader(): void
    {
        $t = new FakeTransport(self::json(402, ['error' => ['code' => 'insufficient_balance', 'message' => 'Not enough credits']], ['X-Request-Id' => 'gw_9']));
        $e = self::catchError(function () use ($t) {
            $this->client($t)->sms->send(['from' => 'QUBA', 'to' => '0771234567', 'message' => 'm']);
        });
        $this->assertInstanceOf(PaymentRequiredError::class, $e);
        $this->assertSame('gw_9', $e->requestId);
    }

    // ------------------------------------------------------------ retries

    public function testRetriesARateLimitWithTheSameIdempotencyKey(): void
    {
        $t = new FakeTransport(self::json(429, ['error' => ['code' => 'rate_limited', 'message' => 'Slow down']], ['Retry-After' => '3']), self::json(202, ['campaign_id' => 'c']));
        $this->client($t)->sms->send(['from' => 'QUBA', 'to' => '0771234567', 'message' => 'm']);
        $this->assertCount(2, $t->calls);
        $this->assertSame($t->calls[0]['headers']['Idempotency-Key'], $t->calls[1]['headers']['Idempotency-Key']);
        $this->assertSame([3000], $this->slept);
    }

    public function testGivesUpWithRateLimitErrorAfterMaxRetries(): void
    {
        $limited = self::json(429, [], ['Retry-After' => '0']);
        $t = new FakeTransport($limited, $limited);
        $e = self::catchError(function () use ($t) {
            $this->client($t, ['maxRetries' => 1])->balance();
        });
        $this->assertInstanceOf(RateLimitError::class, $e);
        $this->assertSame(0, $e->retryAfter);
        $this->assertCount(2, $t->calls);
    }

    public function testRetriesSafeCallsAfterServerOrNetworkErrors(): void
    {
        $t = new FakeTransport(self::json(503), new TransportException('connection reset'), self::json(200, ['credits' => 1]));
        $this->assertSame(1, $this->client($t)->balance()['credits']);
        $this->assertCount(3, $t->calls);
    }

    public function testNeverRetriesAPosPointsCallAfterServerOrNetworkErrors(): void
    {
        $t1 = new FakeTransport(self::json(502));
        $e1 = self::catchError(function () use ($t1) {
            $this->client($t1)->pos->transaction(['phone' => '0771234567', 'amount' => 10]);
        });
        $this->assertInstanceOf(ServerError::class, $e1);
        $this->assertCount(1, $t1->calls);

        $t2 = new FakeTransport(new TransportException('socket hang up'));
        $e2 = self::catchError(function () use ($t2) {
            $this->client($t2)->pos->redemptions->confirm(['phone' => '0771234567', 'redemptionId' => 'r', 'pin' => '1']);
        });
        $this->assertInstanceOf(ConnectionError::class, $e2);
        $this->assertSame('connection_error', $e2->errorCode);
        $this->assertCount(1, $t2->calls);
    }

    public function testReportsATimeout(): void
    {
        $t = new FakeTransport(new TransportException('Operation timed out', true));
        $e = self::catchError(function () use ($t) {
            $this->client($t, ['maxRetries' => 0])->pos->resolve(['phone' => '1']);
        });
        $this->assertInstanceOf(ConnectionError::class, $e);
        $this->assertSame('timeout', $e->errorCode);
    }

    // ------------------------------------------------------------ phone, version

    public function testNormalizePhone(): void
    {
        foreach (['0771234567', '771234567', '+94 77 123 4567', '0094-77-1234567', '94771234567'] as $in) {
            $this->assertSame('94771234567', Phone::normalize($in), $in);
        }
        $this->assertNull(Phone::normalize('0112345678'));
        $this->assertNull(Phone::normalize('12345'));
    }

    public function testVersionIsSet(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Version::VERSION);
    }
}
