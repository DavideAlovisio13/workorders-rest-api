<?php
/**
 * Seed sample work orders.  Usage:  php bin/seed.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use WorkOrders\Domain\WorkOrderRepository;
use WorkOrders\Support\Database;

$config = workorders_config();
$pdo = (new Database($config['db_path']))->pdo();
$pdo->exec('DELETE FROM work_orders');

$repo = new WorkOrderRepository($pdo);

$samples = [
    ['code' => 'WO-2026-001', 'title' => 'CNC line retrofit',        'customer' => 'Acme Manufacturing', 'status' => 'in_progress', 'priority' => 'high',   'assignee' => 'davide',  'hours_estimated' => 40, 'hours_spent' => 22, 'due_date' => '2026-12-15'],
    ['code' => 'WO-2026-002', 'title' => 'ERP data migration',       'customer' => 'Beta Logistics',     'status' => 'open',        'priority' => 'urgent', 'assignee' => null,      'hours_estimated' => 24, 'hours_spent' => 0,  'due_date' => '2026-12-01'],
    ['code' => 'WO-2026-003', 'title' => 'Quarterly maintenance',    'customer' => 'Acme Manufacturing', 'status' => 'done',        'priority' => 'medium', 'assignee' => 'marco',   'hours_estimated' => 8,  'hours_spent' => 7,  'due_date' => '2026-11-10'],
    ['code' => 'WO-2026-004', 'title' => 'Network segmentation',     'customer' => 'Gamma Services',     'status' => 'on_hold',     'priority' => 'high',   'assignee' => 'davide',  'hours_estimated' => 30, 'hours_spent' => 5,  'due_date' => null],
    ['code' => 'WO-2026-005', 'title' => 'Safety audit follow-up',   'customer' => 'Delta Foods',        'status' => 'cancelled',   'priority' => 'low',    'assignee' => null,      'hours_estimated' => 12, 'hours_spent' => 2,  'due_date' => null],
];

foreach ($samples as $s) {
    $repo->create($s);
}

echo "Seeded " . count($samples) . " work orders into {$config['db_path']}\n";
[$rows, $total] = $repo->list([], 1, 100, 'id', 'asc');
foreach ($rows as $r) {
    echo sprintf("  #%d  %-12s %-26s %-12s %s\n", $r['id'], $r['code'], $r['title'], $r['status'], $r['priority']);
}
