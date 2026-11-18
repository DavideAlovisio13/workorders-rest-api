<?php

declare(strict_types=1);

namespace WorkOrders\Support;

/**
 * Tiny regex router. Patterns use {name} placeholders, e.g. /api/work-orders/{id}.
 * Handlers are callables: fn(Request $req, array $params): Response
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = ['method' => strtoupper($method), 'regex' => $regex, 'handler' => $handler];
    }

    public function get(string $p, callable $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST', $p, $h); }
    public function put(string $p, callable $h): void    { $this->add('PUT', $p, $h); }
    public function patch(string $p, callable $h): void  { $this->add('PATCH', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(Request $req): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $r) {
            if (preg_match($r['regex'], $req->path, $m)) {
                $pathMatched = true;
                if ($r['method'] === $req->method) {
                    $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                    return ($r['handler'])($req, $params);
                }
            }
        }
        if ($pathMatched) {
            return Response::error('Method not allowed', 405);
        }
        return Response::error('Not found', 404);
    }
}
