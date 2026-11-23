<?php

namespace Loyvo\Http;

/** No response was received (network error or timeout). */
class TransportException extends \RuntimeException
{
    /** @var bool */
    public $timedOut;

    public function __construct(string $message, bool $timedOut = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->timedOut = $timedOut;
    }
}
