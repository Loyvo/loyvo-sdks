<?php

namespace Loyvo\Exception;

/** Any error the Loyvo API returned, or a failure to reach it. */
class LoyvoException extends \RuntimeException
{
    /** @var int HTTP status (0 when the API could not be reached). */
    public $status;
    /** @var string Stable machine-readable code, e.g. "insufficient_balance". */
    public $errorCode;
    /** @var string|null The input field the error is about. */
    public $field;
    /** @var string|null Quote this to support@loyvo.lk. */
    public $requestId;
    /** @var mixed The decoded response body. */
    public $body;

    /** @param mixed $body */
    public function __construct(string $message, int $status, string $errorCode, ?string $field = null, ?string $requestId = null, $body = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->field = $field;
        $this->requestId = $requestId;
        $this->body = $body;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
