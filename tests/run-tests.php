<?php
/**
 * Dependency-free test suite for the Work Orders REST API.
 * Exercises the repository, controller (validation + RBAC), router and auth
 * against a throwaway SQLite database. No PHPUnit required.
 *
 * Run:  php tests/run-tests.php
 */

declare(strict_types=1);

$testDb = __DIR__ . '/../data/test-' . getmypid() . '.sqlite';
putenv('WORKORDERS_DB=' . $testDb);

require __DIR__ . '/../src/bootstrap.php';

use WorkOrders\Auth\TokenAuth;
use WorkOrders\Domain\WorkOrderRepository;
use WorkOrders\Http\WorkOrderController;
use WorkOrders\Support\Database;
use WorkOrders\Support\Request;
use WorkOrders\Support\Response;
use WorkOrders\Support\Router;

$config = workorders_config();

$passed = 0;
$failed = 0;
function check(string $label, bool $cond): void
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "  \033[32m✓\033[0m $label\n"; }
    else       { $failed++; echo "  \033[31m✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[1;36m$t\033[0m\n"; }

$admin  = ['client' => 'ops-admin', 'role' => 'admin'];
$viewer = ['client' => 'dashboard', 'role' => 'viewer'];

function req(string $method, string $path, array $body = [], array $query = [], ?array $identity = null): Request
{
    $r = new Request($method, $path, $query, $body, []);
    $r->identity = $identity;
    return $r;
}

@unlink($testDb);
$db = new Database($config['db_path']);
$repo = new WorkOrderRepository($db->pdo());
$ctrl = new WorkOrderController($repo, $config);

$valid = [
    'code' => 'WO-2026-001', 'title' => 'CNC retrofit', 'customer' => 'Acme',
    'priority' => 'high', 'hours_estimated' => 10,
];

section('Create');
$r = $ctrl->store(req('POST', '/api/work-orders', $valid, [], $admin), []);
check('valid create -> 201', $r->status === 201);
check('response has id', isset($r->body['data']['id']) && $r->body['data']['id'] > 0);
check('status defaults to open', $r->body['data']['status'] === 'open');
$firstId = $r->body['data']['id'];

$r = $ctrl->store(req('POST', '/api/work-orders', ['customer' => 'X'], [], $admin), []);
check('missing title+code -> 422', $r->status === 422);
check('422 has field details', isset($r->body['error']['details']['title'], $r->body['error']['details']['code']));

$r = $ctrl->store(req('POST', '/api/work-orders', $valid, [], $admin), []);
check('duplicate code -> 409', $r->status === 409);

$r = $ctrl->store(req('POST', '/api/work-orders', ['code' => 'WO-X', 'title' => 'T', 'customer' => 'C', 'status' => 'bogus'], [], $admin), []);
check('invalid status enum -> 422', $r->status === 422);

section('RBAC');
$r = $ctrl->store(req('POST', '/api/work-orders', ['code' => 'WO-9', 'title' => 'T', 'customer' => 'C'], [], $viewer), []);
check('viewer cannot create -> 403', $r->status === 403);

section('Read / list');
for ($i = 2; $i <= 6; $i++) {
    $ctrl->store(req('POST', '/api/work-orders', [
        'code' => "WO-2026-00$i", 'title' => "Job $i", 'customer' => ($i % 2 ? 'Acme' : 'Beta'),
        'status' => ($i % 2 ? 'open' : 'done'),
    ], [], $admin), []);
}
$r = $ctrl->index(req('GET', '/api/work-orders', [], ['per_page' => '2', 'page' => '1'], $viewer), []);
check('list paginates (2 per page)', count($r->body['data']) === 2);
check('meta.total counts all', $r->body['meta']['total'] === 6);
check('meta.total_pages computed', $r->body['meta']['total_pages'] === 3);

$r = $ctrl->index(req('GET', '/api/work-orders', [], ['status' => 'done'], $viewer), []);
check('filter by status', count($r->body['data']) >= 1 && array_values(array_unique(array_column($r->body['data'], 'status'))) === ['done']);

$r = $ctrl->index(req('GET', '/api/work-orders', [], ['q' => 'Job 3'], $viewer), []);
check('search by q', count($r->body['data']) === 1);

$r = $ctrl->show(req('GET', "/api/work-orders/$firstId", [], [], $viewer), ['id' => (string) $firstId]);
check('show existing -> 200', $r->status === 200 && $r->body['data']['id'] === $firstId);
$r = $ctrl->show(req('GET', '/api/work-orders/99999', [], [], $viewer), ['id' => '99999']);
check('show missing -> 404', $r->status === 404);

section('Update');
$r = $ctrl->update(req('PATCH', "/api/work-orders/$firstId", ['status' => 'done', 'hours_spent' => 9], [], $admin), ['id' => (string) $firstId]);
check('patch -> 200', $r->status === 200);
check('status updated', $r->body['data']['status'] === 'done');
check('hours_spent updated', $r->body['data']['hours_spent'] === 9.0);

$r = $ctrl->update(req('PATCH', "/api/work-orders/$firstId", ['status' => 'nope'], [], $admin), ['id' => (string) $firstId]);
check('invalid update -> 422', $r->status === 422);

$r = $ctrl->update(req('PATCH', '/api/work-orders/99999', ['status' => 'open'], [], $admin), ['id' => '99999']);
check('update missing -> 404', $r->status === 404);

$r = $ctrl->update(req('PATCH', "/api/work-orders/$firstId", ['due_date' => '2026-13-40'], [], $admin), ['id' => (string) $firstId]);
check('invalid due_date -> 422', $r->status === 422);

section('Delete');
$r = $ctrl->destroy(req('DELETE', "/api/work-orders/$firstId", [], [], $admin), ['id' => (string) $firstId]);
check('delete -> 204', $r->status === 204);
$r = $ctrl->destroy(req('DELETE', "/api/work-orders/$firstId", [], [], $admin), ['id' => (string) $firstId]);
check('delete again -> 404', $r->status === 404);

section('Auth (TokenAuth)');
$ta = new TokenAuth($config['api_keys']);
$reqAdmin = new Request('GET', '/x', [], [], ['Authorization' => 'Bearer admin-key-123']);
$reqBad = new Request('GET', '/x', [], [], ['Authorization' => 'Bearer nope']);
$reqNone = new Request('GET', '/x', [], [], []);
check('valid key authenticates as admin', ($ta->authenticate($reqAdmin)['role'] ?? '') === 'admin');
check('invalid key -> null', $ta->authenticate($reqBad) === null);
check('no header -> null', $ta->authenticate($reqNone) === null);
check('isWriter true for admin', TokenAuth::isWriter($admin));
check('isWriter false for viewer', !TokenAuth::isWriter($viewer));

section('Router');
$router = new Router();
$router->get('/api/work-orders/{id}', fn($rq, $p) => Response::json(['id' => $p['id']]));
$res = $router->dispatch(new Request('GET', '/api/work-orders/42', [], [], []));
check('router extracts {id}', $res->body['id'] === '42');
check('unknown path -> 404', $router->dispatch(new Request('GET', '/nope', [], [], []))->status === 404);
check('wrong method -> 405', $router->dispatch(new Request('POST', '/api/work-orders/42', [], [], []))->status === 405);

// cleanup
$ctrl = $repo = $db = null;
gc_collect_cycles();
foreach (['', '-wal', '-shm'] as $s) {
    @unlink($testDb . $s);
}

echo "\n" . ($failed === 0
    ? "\033[1;32mAll $passed tests passed.\033[0m\n"
    : "\033[1;31m$failed failed, $passed passed.\033[0m\n");
exit($failed === 0 ? 0 : 1);
