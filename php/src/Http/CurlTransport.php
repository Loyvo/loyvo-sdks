<?php

namespace Loyvo\Http;

final class CurlTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): Response
    {
        if (!function_exists('curl_init')) {
            throw new TransportException('The PHP curl extension is required (or pass your own transport).');
        }
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => (int) min(10000, $timeoutSeconds * 1000),
            CURLOPT_TIMEOUT_MS => (int) ($timeoutSeconds * 1000),
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$respHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        if ($out === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            if (PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }
            throw new TransportException($error !== '' ? $error : 'Request failed', $errno === CURLE_OPERATION_TIMEDOUT);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
        return new Response($status, $respHeaders, (string) $out);
    }
}
