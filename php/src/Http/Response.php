<?php

namespace Loyvo\Http;

/** What a transport got back. Header names are lower-case. */
final class Response
{
    /** @var int */
    public $status;
    /** @var array<string, string> */
    public $headers;
    /** @var string */
    public $body;

    /** @param array<string, string> $headers */
    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
    }

    public function header(string $name): ?string
    {
        $k = strtolower($name);
        return isset($this->headers[$k]) ? $this->headers[$k] : null;
    }
}
