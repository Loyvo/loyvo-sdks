<?php

namespace Loyvo\Exception;

use Loyvo\Http\Response;

/**
 * Reads the error shapes the Loyvo APIs use into one exception: SMS
 * {error:{code,message,field}}, Wallet {error:"...", code}, Loyalty
 * {status:"error", errors:{field:[...]}}, {message} or {detail}.
 */
final class ErrorParser
{
    public static function fromResponse(Response $res): LoyvoException
    {
        $body = json_decode($res->body, true);
        if (!is_array($body)) {
            $body = $res->body;
        }
        $b = is_array($body) ? $body : [];
        $code = '';
        $message = '';
        $field = null;
        $requestId = $res->header('x-request-id');

        if (isset($b['error']) && is_array($b['error'])) {
            $code = self::str($b['error']['code'] ?? null);
            $message = self::str($b['error']['message'] ?? null);
            $field = self::str($b['error']['field'] ?? null) ?: null;
            $requestId = self::str($b['error']['request_id'] ?? null) ?: $requestId;
        } elseif (isset($b['error']) && is_string($b['error'])) {
            $message = $b['error'];
        }
        if ($code === '') {
            $code = self::str($b['code'] ?? null) ?: self::str($b['error_code'] ?? null);
        }
        if ($message === '') {
            $message = self::str($b['message'] ?? null) ?: self::str($b['detail'] ?? null);
        }
        if (isset($b['errors']) && is_array($b['errors']) && $b['errors']) {
            $k = (string) array_key_first($b['errors']);
            $v = $b['errors'][$k];
            if ($field === null && $k !== 'non_field_errors' && !is_numeric($k)) {
                $field = $k;
            }
            if ($message === '') {
                $message = is_array($v) ? (string) reset($v) : (string) $v;
            }
        }
        $status = $res->status;
        if ($message === '') {
            $message = is_string($body) && $body !== '' ? substr($body, 0, 300) : 'HTTP ' . $status;
        }
        if ($code === '') {
            $code = self::defaultCode($status);
        }

        $args = [$message, $status, $code, $field, $requestId, $body];
        if ($status === 400 || $status === 422) {
            return new ValidationError(...$args);
        }
        switch ($status) {
            case 401:
                return new AuthenticationError(...$args);
            case 402:
                return new PaymentRequiredError(...$args);
            case 403:
                return new PermissionDeniedError(...$args);
            case 404:
                return new NotFoundError(...$args);
            case 409:
                return new ConflictError(...$args);
            case 429:
                $e = new RateLimitError(...$args);
                $ra = $res->header('retry-after');
                $e->retryAfter = ($ra !== null && is_numeric($ra) && (int) $ra >= 0) ? (int) $ra : null;
                return $e;
        }
        if ($status >= 500) {
            return new ServerError(...$args);
        }
        return new LoyvoException(...$args);
    }

    /** @param mixed $v */
    private static function str($v): string
    {
        return is_string($v) ? $v : '';
    }

    private static function defaultCode(int $status): string
    {
        $codes = [400 => 'invalid_request', 401 => 'unauthorized', 402 => 'payment_required', 403 => 'forbidden', 404 => 'not_found', 409 => 'conflict', 422 => 'validation_failed', 429 => 'rate_limited'];
        if (isset($codes[$status])) {
            return $codes[$status];
        }
        return $status >= 500 ? 'server_error' : 'error';
    }
}
