<?php

declare(strict_types=1);

namespace WorkOrders\Support;

/**
 * Immutable-ish HTTP response value object. Controllers return these so they can be
 * unit-tested without a running web server; index.php calls send().
 */
final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status = 200,
        public mixed $body = null,
        public array $headers = []
    ) {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self($status, $data, $headers);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    /** @param array<string,string>|null $details */
    public static function error(string $message, int $status, ?array $details = null, ?string $code = null): self
    {
        $err = ['error' => ['status' => $status, 'message' => $message]];
        if ($code !== null) {
            $err['error']['code'] = $code;
        }
        if ($details !== null) {
            $err['error']['details'] = $details;
        }
        return new self($status, $err);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        if ($this->status !== 204 && $this->body !== null) {
            echo json_encode($this->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
}
