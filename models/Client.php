<?php

class Client extends Model
{
    protected string $table = 'clients';

    public function findByIdNumber(string $idNumber): ?array
    {
        $row = $this->query("SELECT * FROM clients WHERE id_number = :i", ['i' => $idNumber])->fetch();
        return $row ?: null;
    }

    public function loanCount(int $clientId): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) c FROM loans WHERE client_id = :id",
            ['id' => $clientId]
        )->fetch()['c'];
    }

    public static function groupForCount(int $count): string
    {
        if ($count <= 3)  return 'Group 1';
        if ($count <= 8)  return 'Group 2';
        return 'Group 3';
    }

    public function create(array $d): int
    {
        $this->query(
            "INSERT INTO clients (name, surname, id_number, account_number, phone)
             VALUES (:name, :surname, :id_number, :account_number, :phone)",
            [
                'name'           => $d['name'],
                'surname'        => $d['surname'],
                'id_number'      => $d['id_number'],
                'account_number' => $d['account_number'] ?? null,
                'phone'          => $d['phone'] ?? null,
            ]
        );
        return (int) $this->db->lastInsertId('clients_id_seq');
    }

    public function update(int $id, array $d): bool
    {
        return $this->query(
            "UPDATE clients SET name = :name, surname = :surname, account_number = :account_number, phone = :phone
             WHERE id = :id",
            [
                'name' => $d['name'], 'surname' => $d['surname'],
                'account_number' => $d['account_number'] ?? null, 'phone' => $d['phone'] ?? null,
                'id' => $id,
            ]
        )->rowCount() >= 0;
    }

    /**
     * Find existing client by ID number, or create a new one.
     * Returns the client's row plus loan_count (before the new loan is added) and group.
     */
    public function findOrCreate(array $d): array
    {
        $existing = $this->findByIdNumber($d['id_number']);
        if ($existing) {
            return $existing;
        }
        $newId = $this->create($d);
        return $this->find($newId);
    }
}
