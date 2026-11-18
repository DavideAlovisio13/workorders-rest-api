<?php

declare(strict_types=1);

namespace WorkOrders\Http;

use WorkOrders\Auth\TokenAuth;
use WorkOrders\Domain\WorkOrderRepository;
use WorkOrders\Support\Request;
use WorkOrders\Support\Response;

/** CRUD handlers for /api/work-orders. Each returns a Response (unit-testable). */
final class WorkOrderController
{
    public const STATUSES = ['open', 'in_progress', 'on_hold', 'done', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** @param array<string,mixed> $config */
    public function __construct(private WorkOrderRepository $repo, private array $config)
    {
    }

    public function index(Request $req, array $params): Response
    {
        $cfg = $this->config['pagination'];
        $perPage = (int) ($req->query('per_page', $cfg['default_per_page']));
        $perPage = max(1, min($perPage, $cfg['max_per_page']));
        $page = max(1, (int) $req->query('page', 1));

        $filters = [
            'status'   => $req->query('status'),
            'priority' => $req->query('priority'),
            'q'        => $req->query('q'),
        ];
        $sort = (string) $req->query('sort', 'id');
        $order = (string) $req->query('order', 'asc');

        [$rows, $total] = $this->repo->list($filters, $page, $perPage, $sort, $order);

        return Response::json([
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function show(Request $req, array $params): Response
    {
        $wo = $this->repo->find((int) $params['id']);
        return $wo ? Response::json(['data' => $wo]) : Response::error('Work order not found', 404);
    }

    public function store(Request $req, array $params): Response
    {
        if (($guard = $this->requireWriter($req)) !== null) {
            return $guard;
        }
        [$data, $errors] = $this->validate($req->body, true);
        if ($errors) {
            return Response::error('Validation failed', 422, $errors);
        }
        if ($this->repo->codeExists($data['code'])) {
            return Response::error("Work order code '{$data['code']}' already exists", 409);
        }
        $wo = $this->repo->create($data);
        return Response::json(['data' => $wo], 201, ['Location' => '/api/work-orders/' . $wo['id']]);
    }

    public function update(Request $req, array $params): Response
    {
        if (($guard = $this->requireWriter($req)) !== null) {
            return $guard;
        }
        $id = (int) $params['id'];
        if ($this->repo->find($id) === null) {
            return Response::error('Work order not found', 404);
        }
        [$data, $errors] = $this->validate($req->body, false);
        if ($errors) {
            return Response::error('Validation failed', 422, $errors);
        }
        if (isset($data['code']) && $this->repo->codeExists($data['code'], $id)) {
            return Response::error("Work order code '{$data['code']}' already exists", 409);
        }
        if ($data === []) {
            return Response::error('No valid fields to update', 422);
        }
        return Response::json(['data' => $this->repo->update($id, $data)]);
    }

    public function destroy(Request $req, array $params): Response
    {
        if (($guard = $this->requireWriter($req)) !== null) {
            return $guard;
        }
        return $this->repo->delete((int) $params['id'])
            ? Response::noContent()
            : Response::error('Work order not found', 404);
    }

    // ------------------------------------------------------------ helpers

    private function requireWriter(Request $req): ?Response
    {
        if (!TokenAuth::isWriter($req->identity)) {
            return Response::error('Forbidden: admin role required for write operations', 403);
        }
        return null;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function validate(array $input, bool $isCreate): array
    {
        $data = [];
        $errors = [];

        $str = static fn ($v): string => trim((string) $v);

        // code
        if ($isCreate || array_key_exists('code', $input)) {
            $code = $str($input['code'] ?? '');
            if ($code === '') {
                $errors['code'] = 'code is required';
            } elseif (strlen($code) > 40) {
                $errors['code'] = 'code must be <= 40 characters';
            } else {
                $data['code'] = $code;
            }
        }
        // title
        if ($isCreate || array_key_exists('title', $input)) {
            $title = $str($input['title'] ?? '');
            if ($title === '') {
                $errors['title'] = 'title is required';
            } elseif (strlen($title) > 200) {
                $errors['title'] = 'title must be <= 200 characters';
            } else {
                $data['title'] = $title;
            }
        }
        // customer
        if ($isCreate || array_key_exists('customer', $input)) {
            $customer = $str($input['customer'] ?? '');
            if ($customer === '') {
                $errors['customer'] = 'customer is required';
            } else {
                $data['customer'] = $customer;
            }
        }
        // status
        if (array_key_exists('status', $input)) {
            $status = $str($input['status']);
            if (!in_array($status, self::STATUSES, true)) {
                $errors['status'] = 'status must be one of: ' . implode(', ', self::STATUSES);
            } else {
                $data['status'] = $status;
            }
        } elseif ($isCreate) {
            $data['status'] = 'open';
        }
        // priority
        if (array_key_exists('priority', $input)) {
            $priority = $str($input['priority']);
            if (!in_array($priority, self::PRIORITIES, true)) {
                $errors['priority'] = 'priority must be one of: ' . implode(', ', self::PRIORITIES);
            } else {
                $data['priority'] = $priority;
            }
        } elseif ($isCreate) {
            $data['priority'] = 'medium';
        }
        // assignee (optional, nullable)
        if (array_key_exists('assignee', $input)) {
            $data['assignee'] = $input['assignee'] === null ? null : $str($input['assignee']);
        }
        // hours
        foreach (['hours_estimated', 'hours_spent'] as $hk) {
            if (array_key_exists($hk, $input)) {
                if (!is_numeric($input[$hk]) || (float) $input[$hk] < 0) {
                    $errors[$hk] = "$hk must be a number >= 0";
                } else {
                    $data[$hk] = (float) $input[$hk];
                }
            }
        }
        // due_date (YYYY-MM-DD)
        if (array_key_exists('due_date', $input)) {
            $dd = $input['due_date'];
            if ($dd === null || $dd === '') {
                $data['due_date'] = null;
            } elseif ($this->isValidDate((string) $dd)) {
                $data['due_date'] = (string) $dd;
            } else {
                $errors['due_date'] = 'due_date must be a valid date (YYYY-MM-DD)';
            }
        }

        return [$data, $errors];
    }

    private function isValidDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }
}
