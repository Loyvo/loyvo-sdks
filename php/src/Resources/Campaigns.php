<?php

namespace Loyvo\Resources;

use Loyvo\Http\Client;

final class Campaigns
{
    /** @var Client */
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /**
     * A campaign with a page of its messages.
     * @return array{campaign: array<string, mixed>, messages: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function get(string $id, int $page = 1, int $pageSize = 0): array
    {
        return $this->http->request('GET', '/v1/campaigns/' . rawurlencode($id), [
            'query' => ['page' => $page > 1 ? $page : null, 'page_size' => $pageSize > 0 ? $pageSize : null],
        ]);
    }

    /** Cancel a scheduled campaign; its credits are returned. @return array<string, mixed> */
    public function cancel(string $id): array
    {
        return $this->http->request('POST', '/v1/campaigns/' . rawurlencode($id) . '/cancel', ['retrySafe' => true]);
    }
}
