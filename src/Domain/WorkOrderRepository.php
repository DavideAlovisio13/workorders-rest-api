<?php

declare(strict_types=1);

namespace WorkOrders\Domain;

use PDO;

/** Data access for work orders, including filtered/paginated/sorted listing. */
final class WorkOrderRepository
{
    private const SORTABLE = ['id', 'code', 'title', 'customer', 'status', 'priority', 'due_date', 'created_at', 'updated_at'];
    private const FIELDS = ['code', 'title', 'customer', 'status', 'priority', 'assignee', 'hours_estimated', 'hours_spent', 'due_date'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $filters  status, priority, q
     * @return array{0:array<int,array<string,mixed>>,1:int}  [rows, total]
     */
    public function list(array $filters, int $page, int $perPage, string $sort, string $order): array
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'id';
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'priority = :priority';
            $params[':priority'] = $filters['priority'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(title LIKE :q OR customer LIKE :q OR code LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        $clause = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM work_orders' . $clause);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM work_orders$clause ORDER BY $sort $order LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [array_map([$this, 'cast'], $stmt->fetchAll()), $total];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM work_orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->cast($row);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM work_orders WHERE code = :code';
        $params = [':code' => $code];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): array
    {
        $now = gmdate('c');
        // Only insert provided fields; let the schema defaults fill the rest
        // (binding an explicit NULL would violate NOT NULL columns like hours_*).
        $cols = array_values(array_intersect(self::FIELDS, array_keys($data)));
        $allCols = array_merge($cols, ['created_at', 'updated_at']);
        $place = array_map(static fn ($c) => ':' . $c, $allCols);
        $sql = 'INSERT INTO work_orders (' . implode(',', $allCols) . ') VALUES (' . implode(',', $place) . ')';
        $stmt = $this->pdo->prepare($sql);
        $bind = [':created_at' => $now, ':updated_at' => $now];
        foreach ($cols as $c) {
            $bind[':' . $c] = $data[$c];
        }
        $stmt->execute($bind);
        return $this->find((int) $this->pdo->lastInsertId());
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): array
    {
        $set = [];
        $bind = [':id' => $id, ':updated_at' => gmdate('c')];
        foreach (self::FIELDS as $c) {
            if (array_key_exists($c, $data)) {
                $set[] = "$c = :$c";
                $bind[':' . $c] = $data[$c];
            }
        }
        $set[] = 'updated_at = :updated_at';
        $stmt = $this->pdo->prepare('UPDATE work_orders SET ' . implode(',', $set) . ' WHERE id = :id');
        $stmt->execute($bind);
        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM work_orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function cast(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['hours_estimated'] = (float) $row['hours_estimated'];
        $row['hours_spent'] = (float) $row['hours_spent'];
        return $row;
    }
}
