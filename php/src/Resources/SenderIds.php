<?php

namespace Loyvo\Resources;

use Loyvo\Http\Client;

final class SenderIds
{
    /** @var Client */
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /** Your sender IDs; only "approved" ones can send. @return array<int, array<string, mixed>> */
    public function list(): array
    {
        $r = $this->http->request('GET', '/v1/sender-ids');
        return $r['data'] ?? [];
    }
}
