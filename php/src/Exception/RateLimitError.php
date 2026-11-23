<?php

namespace Loyvo\Exception;

/** 429: too many requests. Wait $retryAfter seconds. */
class RateLimitError extends LoyvoException
{
    /** @var int|null */
    public $retryAfter;
}
