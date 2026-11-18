<?php

declare(strict_types=1);

namespace WorkOrders\Support;

/** HTTP request value object (buildable from globals or by hand for tests). */
final class Request
{
    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @param array<string,string> $headers
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $body = [],
        public array $headers = []
    ) {
    }

    /** Authenticated identity, set by the auth middleware: {client, role} or null. */
    public ?array $identity = null;

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $query);

        $body = [];
        $raw = file_get_contents('php://input') ?: '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self($method, $path, $query, $body, self::collectHeaders());
    }

    public function header(string $name): ?string
    {
        $name = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $name) {
                return $v;
            }
        }
        return null;
    }

    public function query(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    /** @return array<string,string> */
    private static function collectHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (is_array($h)) {
                $headers = $h;
            }
        }
        // Fallback (e.g. CLI server sometimes): reconstruct from $_SERVER.
        if ($headers === []) {
            foreach ($_SERVER as $k => $v) {
                if (str_starts_with($k, 'HTTP_')) {
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                    $headers[$name] = $v;
                }
            }
        }
        return $headers;
    }
}
