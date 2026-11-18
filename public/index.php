<?php
/**
 * Front controller / router for the Work Orders REST API.
 *
 * Run (built-in server, routes everything here):
 *   php -S 127.0.0.1:8080 public/index.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use WorkOrders\Auth\TokenAuth;
use WorkOrders\Domain\WorkOrderRepository;
use WorkOrders\Http\WorkOrderController;
use WorkOrders\Support\Database;
use WorkOrders\Support\Request;
use WorkOrders\Support\Response;
use WorkOrders\Support\Router;

$config = workorders_config();
$req = Request::fromGlobals();

// --- authentication middleware ---
$auth = new TokenAuth($config['api_keys']);
$req->identity = $auth->authenticate($req);

$repo = new WorkOrderRepository((new Database($config['db_path']))->pdo());
$controller = new WorkOrderController($repo, $config);

$router = new Router();
$router->get('/api/health', fn() => Response::json(['status' => 'ok', 'service' => 'workorders-rest-api']));
$router->get('/api/work-orders', [$controller, 'index']);
$router->post('/api/work-orders', [$controller, 'store']);
$router->get('/api/work-orders/{id}', [$controller, 'show']);
$router->put('/api/work-orders/{id}', [$controller, 'update']);
$router->patch('/api/work-orders/{id}', [$controller, 'update']);
$router->delete('/api/work-orders/{id}', [$controller, 'destroy']);

// Root: a tiny index so the base URL is friendly.
$router->get('/', fn() => Response::json([
    'service' => 'workorders-rest-api',
    'docs' => 'see openapi.yaml',
    'endpoints' => ['/api/health', '/api/work-orders'],
]));

try {
    // Require authentication for everything under /api/work-orders.
    if (str_starts_with($req->path, '/api/work-orders') && $req->identity === null) {
        Response::error('Unauthorized: provide a valid Bearer API key', 401)->send();
        return;
    }
    $router->dispatch($req)->send();
} catch (\Throwable $e) {
    // Never leak internals to the client.
    error_log('[workorders] ' . $e->getMessage());
    Response::error('Internal server error', 500)->send();
}
