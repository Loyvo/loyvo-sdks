<?php

namespace Loyvo\Http;

/**
 * Sends one HTTP request. The default is CurlTransport; WordPress plugins
 * can pass one built on wp_remote_request().
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     * @throws TransportException when no response was received
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): Response;
}
