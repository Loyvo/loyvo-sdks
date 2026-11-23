<?php

namespace Loyvo\Resources;

use Loyvo\Http\Client;

/** The business's loyalty card designs. Pass a template's id as cardTemplateId when enrolling. */
final class CardTemplates
{
    /** @var Client */
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /**
     * Every card template, newest first. Only "PUBLISHED" ones can issue cards.
     * @return array<int, array<string, mixed>> id, name, type, status, ...
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/wallet/card-templates') ?? [];
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/wallet/card-templates/' . rawurlencode($id));
    }
}
