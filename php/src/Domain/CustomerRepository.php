<?php
declare(strict_types=1);

namespace Tds\Ext\Customers\Domain;

use PDO;

/**
 * The panel's canonical customer/company directory (`customer`). All access via
 * the core shared PDO. `adminList()` is the lightweight `{id,name}` list the
 * base user-management consumes for company-membership editing (replacing the
 * legacy `tds-customer-api` `GET /admin/customers`).
 */
final class CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, email, phone, note, created_at FROM customer ORDER BY name ASC'
        )->fetchAll();
        return array_map([self::class, 'map'], $rows);
    }

    /** Lightweight `{id,name}` list for membership pickers. @return list<array{id:int,name:string}> */
    public function adminList(): array
    {
        $rows = $this->pdo->query('SELECT id, name FROM customer ORDER BY name ASC')->fetchAll();
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
        ], $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, phone, note, created_at FROM customer WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::map($row);
    }

    /** @param array{name:string,email:?string,phone:?string,note:?string} $d */
    public function create(array $d): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customer (name, email, phone, note) VALUES (:name, :email, :phone, :note)'
        );
        $stmt->execute([':name' => $d['name'], ':email' => $d['email'], ':phone' => $d['phone'], ':note' => $d['note']]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{name:string,email:?string,phone:?string,note:?string} $d */
    public function update(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE customer SET name = :name, email = :email, phone = :phone, note = :note WHERE id = :id'
        );
        $stmt->execute([':id' => $id, ':name' => $d['name'], ':email' => $d['email'], ':phone' => $d['phone'], ':note' => $d['note']]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM customer WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM customer')->fetchColumn();
    }

    /** Whether an email is already taken by a different customer (unique-guard). */
    public function emailTakenBy(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM customer WHERE email = :email';
        $params = [':email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $r */
    private static function map(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'email' => $r['email'] !== null ? (string) $r['email'] : null,
            'phone' => $r['phone'] !== null ? (string) $r['phone'] : null,
            'note' => $r['note'] !== null ? (string) $r['note'] : null,
            'created_at' => isset($r['created_at']) ? (string) $r['created_at'] : null,
        ];
    }
}
