<?php

namespace Loyvo\Resources;

use Loyvo\Http\Client;

/** Wallet cards (Apple Wallet and Google Wallet). Needs a live key with wallet:issue. */
final class Passes
{
    /** @var Client */
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/wallet/passes/' . rawurlencode($id));
    }

    /** The Apple Wallet card file (.pkpass) as bytes. */
    public function applePass(string $id): string
    {
        return $this->http->request('GET', '/v1/wallet/passes/' . rawurlencode($id) . '/apple.pkpass', ['raw' => true]);
    }

    /** A "Save to Google Wallet" link. */
    public function googleSaveUrl(string $id): string
    {
        $r = $this->http->request('GET', '/v1/wallet/passes/' . rawurlencode($id) . '/google-save-url');
        return (string) ($r['saveUrl'] ?? '');
    }

    /**
     * Change the card's points or stamps (does not change Loyvo Loyalty's ledger).
     * @param array{pointsDelta?: int, stampsDelta?: int} $p
     * @return array<string, mixed>
     */
    public function updateBalance(string $id, array $p): array
    {
        return $this->http->request('PATCH', '/v1/wallet/passes/' . rawurlencode($id) . '/balance', [
            'body' => ['pointsDelta' => $p['pointsDelta'] ?? null, 'stampsDelta' => $p['stampsDelta'] ?? null],
        ]);
    }

    /** Void the card. @return array<string, mixed> */
    public function void(string $id): array
    {
        return $this->http->request('POST', '/v1/wallet/passes/' . rawurlencode($id) . '/void', ['retrySafe' => true]);
    }
}
