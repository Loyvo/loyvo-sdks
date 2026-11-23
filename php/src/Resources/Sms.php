<?php

namespace Loyvo\Resources;

use Loyvo\Http\Client;
use Loyvo\Phone;

final class Sms
{
    /** @var Client */
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /**
     * Send one message to one or more numbers (up to 100).
     *
     * @param array{from: string, to: string|string[], message: string, reference?: string, scheduleAt?: string|\DateTimeInterface, idempotencyKey?: string} $p
     * @return array<string, mixed> campaign_id, status, segments, test, ...
     */
    public function send(array $p): array
    {
        return $this->http->request('POST', '/v1/sms/send', [
            'body' => [
                'sender_id' => $p['from'],
                'to' => $p['to'],
                'message' => $p['message'],
                'reference' => $p['reference'] ?? null,
                'schedule_at' => self::iso($p['scheduleAt'] ?? null),
            ],
            'idempotencyKey' => $p['idempotencyKey'] ?? Phone::newIdempotencyKey(),
        ]);
    }

    /**
     * Send a personalised campaign (up to 10,000 recipients); {{placeholders}} come from each recipient's vars.
     *
     * @param array{from: string, message: string, recipients: array<int, array{to: string, vars?: array<string, string|int>}>, name?: string, reference?: string, scheduleAt?: string|\DateTimeInterface, idempotencyKey?: string} $p
     * @return array<string, mixed>
     */
    public function bulk(array $p): array
    {
        return $this->http->request('POST', '/v1/sms/bulk', [
            'body' => [
                'sender_id' => $p['from'],
                'name' => $p['name'] ?? null,
                'message' => $p['message'],
                'recipients' => $p['recipients'],
                'reference' => $p['reference'] ?? null,
                'schedule_at' => self::iso($p['scheduleAt'] ?? null),
            ],
            'idempotencyKey' => $p['idempotencyKey'] ?? Phone::newIdempotencyKey(),
        ]);
    }

    /** @return array<string, mixed> one message and its delivery status */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/sms/' . rawurlencode($id));
    }

    /** @param string|\DateTimeInterface|null $v */
    private static function iso($v): ?string
    {
        return $v instanceof \DateTimeInterface ? $v->format(\DateTimeInterface::ATOM) : $v;
    }
}
