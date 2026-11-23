<?php

namespace Loyvo\Http;

use Loyvo\Exception\ConnectionError;
use Loyvo\Exception\ErrorParser;
use Loyvo\Exception\LoyvoException;
use Loyvo\Version;

/** @internal Sends requests to the Loyvo API with retries. */
final class Client
{
    /** @var string */
    public $apiKey;
    /** @var string */
    private $baseUrl;
    /** @var float */
    private $timeout;
    /** @var int */
    private $maxRetries;
    /** @var Transport */
    private $transport;
    /** @var callable(int):void sleeps for the given milliseconds */
    private $sleep;

    /** @param array<string, mixed> $opts */
    public function __construct(array $opts)
    {
        $key = $opts['apiKey'] ?? getenv('LOYVO_API_KEY');
        if (!is_string($key) || $key === '') {
            throw new LoyvoException('Missing API key: pass ["apiKey" => ...] or set LOYVO_API_KEY.', 0, 'missing_api_key');
        }
        $this->apiKey = $key;
        $this->baseUrl = rtrim((string) ($opts['baseUrl'] ?? 'https://api.loyvo.lk'), '/');
        $this->timeout = (float) ($opts['timeout'] ?? 30);
        $this->maxRetries = max(0, (int) ($opts['maxRetries'] ?? 2));
        $this->transport = $opts['transport'] ?? new CurlTransport();
        $this->sleep = $opts['sleep'] ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /**
     * @param array{query?: array<string, mixed>, body?: mixed, idempotencyKey?: string, retrySafe?: bool, raw?: bool} $opts
     * @return mixed decoded JSON (associative arrays), the raw body when "raw", or null for an empty response
     */
    public function request(string $method, string $path, array $opts = [])
    {
        $url = $this->baseUrl . $path;
        $query = array_filter($opts['query'] ?? [], static function ($v) {
            return $v !== null && $v !== '';
        });
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => !empty($opts['raw']) ? '*/*' : 'application/json',
            'User-Agent' => 'loyvo-php/' . Version::VERSION . ' PHP/' . PHP_VERSION,
        ];
        $body = null;
        if (array_key_exists('body', $opts)) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode(self::dropNulls($opts['body']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (!empty($opts['idempotencyKey'])) {
            $headers['Idempotency-Key'] = (string) $opts['idempotencyKey'];
        }
        $retrySafe = $method === 'GET' || !empty($opts['idempotencyKey']) || !empty($opts['retrySafe']);

        for ($attempt = 0;; $attempt++) {
            try {
                $res = $this->transport->send($method, $url, $headers, $body, $this->timeout);
            } catch (TransportException $e) {
                // No answer: retry only when that can't do something twice.
                if ($retrySafe && $attempt < $this->maxRetries) {
                    ($this->sleep)($this->backoff($attempt));
                    continue;
                }
                throw new ConnectionError(
                    $e->timedOut ? sprintf('Request timed out after %s s', $this->timeout) : 'Could not reach Loyvo: ' . $e->getMessage(),
                    0,
                    $e->timedOut ? 'timeout' : 'connection_error',
                    null,
                    null,
                    null,
                    $e
                );
            }

            if ($res->status >= 200 && $res->status < 300) {
                if (!empty($opts['raw'])) {
                    return $res->body;
                }
                if ($res->status === 204 || $res->body === '') {
                    return null;
                }
                return json_decode($res->body, true);
            }

            $error = ErrorParser::fromResponse($res);
            // 429 means it was not processed, so it is always safe to try again.
            $retry = $attempt < $this->maxRetries && ($res->status === 429 || ($retrySafe && in_array($res->status, [500, 502, 503, 504], true)));
            if (!$retry) {
                throw $error;
            }
            $ra = $res->header('retry-after');
            ($this->sleep)($ra !== null && is_numeric($ra) && (int) $ra > 0 ? min((int) $ra, 60) * 1000 : $this->backoff($attempt));
        }
    }

    private function backoff(int $attempt): int
    {
        return (int) (min(8000, 500 * (2 ** $attempt)) * (0.75 + mt_rand() / mt_getrandmax() * 0.5));
    }

    /**
     * Leaves out optional fields that were not given (top level only).
     * @param mixed $body
     * @return mixed
     */
    private static function dropNulls($body)
    {
        if (!is_array($body)) {
            return $body;
        }
        return array_filter($body, static function ($v) {
            return $v !== null;
        });
    }
}
