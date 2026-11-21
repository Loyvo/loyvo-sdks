<?php

namespace Loyvo;

use Loyvo\Http\Client;
use Loyvo\Resources\Campaigns;
use Loyvo\Resources\Loyalty;
use Loyvo\Resources\Passes;
use Loyvo\Resources\Pos;
use Loyvo\Resources\SenderIds;
use Loyvo\Resources\Sms;

/**
 * The Loyvo API: SMS, Loyalty and Wallet with one API key.
 *
 *     $loyvo = new \Loyvo\Loyvo(['apiKey' => getenv('LOYVO_API_KEY')]);
 *     $loyvo->sms->send(['from' => 'QUBA', 'to' => '0771234567', 'message' => 'Hi!']);
 *
 * Options: apiKey (default: LOYVO_API_KEY), baseUrl, timeout (seconds, default 30),
 * maxRetries (default 2), transport (a \Loyvo\Http\Transport).
 */
final class Loyvo
{
    /** @var Sms */
    public $sms;
    /** @var Campaigns */
    public $campaigns;
    /** @var SenderIds */
    public $senderIds;
    /** @var Pos */
    public $pos;
    /** @var Passes */
    public $passes;
    /** @var Loyalty */
    public $loyalty;
    /** @var Client */
    private $http;

    /** @param array<string, mixed> $options */
    public function __construct(array $options = [])
    {
        $this->http = new Client($options);
        $this->sms = new Sms($this->http);
        $this->campaigns = new Campaigns($this->http);
        $this->senderIds = new SenderIds($this->http);
        $this->pos = new Pos($this->http);
        $this->passes = new Passes($this->http);
        $this->loyalty = new Loyalty($this->http);
    }

    /** True for lvo_test_ keys: SMS is free and never delivered; Loyalty and Wallet refuse them. */
    public function isTest(): bool
    {
        return strpos($this->http->apiKey, 'lvo_test_') === 0;
    }

    /**
     * Which organisation this key belongs to, live or test, and what it may do.
     * @return array{key_id: string, env: string, scopes: string[], org: array{id: string, name: string}, products: string[]}
     */
    public function whoami(): array
    {
        return $this->http->request('GET', '/v1/key');
    }

    /** Your SMS credits. @return array<string, mixed> credits, currency, ... */
    public function balance(): array
    {
        return $this->http->request('GET', '/v1/balance');
    }
}
