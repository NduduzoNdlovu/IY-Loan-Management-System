<?php

class Loan extends Model
{
    protected string $table = 'loans';

    // -------------------------------------------------------------
    // Reference number generation: LN-YYYYMMDD-0001 (resets daily)
    // -------------------------------------------------------------
    public function nextReferenceNumber(): string
    {
        $today = date('Y-m-d');

        // $this->db->beginTransaction();
        // try {
        $ownTransaction = !$this->db->inTransaction();

        if ($ownTransaction) {
        $this->db->beginTransaction();
        }

        try {
            $this->query(
                "INSERT INTO daily_counters (counter_date, last_value) VALUES (:d, 0)
                 ON CONFLICT (counter_date) DO NOTHING",
                ['d' => $today]
            );
            $row = $this->query(
                "UPDATE daily_counters SET last_value = last_value + 1
                 WHERE counter_date = :d RETURNING last_value",
                ['d' => $today]
            )->fetch();
            // $this->db->commit();
            if ($ownTransaction) {
                    $this->db->commit();
                }
        } catch (Exception $e) {
            // $this->db->rollBack();
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        $seq = str_pad((string) $row['last_value'], 4, '0', STR_PAD_LEFT);
        return 'LN-' . date('Ymd') . '-' . $seq;
    }

    // -------------------------------------------------------------
    // Create / Update
    // -------------------------------------------------------------
    public function create(array $d): array
    {
        $this->db->beginTransaction();
        try {
            $clientModel = new Client();
            $client = $clientModel->findOrCreate([
                'name'           => $d['name'],
                'surname'        => $d['surname'],
                'id_number'      => $d['id_number'],
                'account_number' => $d['account_number'] ?? null,
                'phone'          => $d['phone'] ?? null,
            ]);

            $reference = $this->nextReferenceNumber();

            $this->query(
                "INSERT INTO loans
                    (reference_number, client_id, branch_id, loan_status_id, report_status_id,
                     amount, action_date, notes, created_by)
                 VALUES
                    (:reference_number, :client_id, :branch_id, :loan_status_id, :report_status_id,
                     :amount, :action_date, :notes, :created_by)",
                [
                    'reference_number' => $reference,
                    'client_id'        => $client['id'],
                    'branch_id'        => $d['branch_id'],
                    'loan_status_id'   => $d['loan_status_id'],
                    'report_status_id' => $d['report_status_id'],
                    'amount'           => $d['amount'],
                    'action_date'      => $d['action_date'],
                    'notes'            => $d['notes'] ?? null,
                    'created_by'       => $d['created_by'] ?? null,
                ]
            );
            $loanId = (int) $this->db->lastInsertId('loans_id_seq');
            $this->db->commit();

            $loanCount = (new Client())->loanCount($client['id']);

            return [
                'loan_id'          => $loanId,
                'reference_number' => $reference,
                'client'           => $client,
                'loan_count'       => $loanCount,
                'group'            => Client::groupForCount($loanCount),
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $d): bool
    {
        return $this->query(
            "UPDATE loans SET branch_id = :branch_id, loan_status_id = :loan_status_id,
                report_status_id = :report_status_id, amount = :amount, action_date = :action_date,
                notes = :notes, updated_at = NOW()
             WHERE id = :id",
            [
                'branch_id'        => $d['branch_id'],
                'loan_status_id'   => $d['loan_status_id'],
                'report_status_id' => $d['report_status_id'],
                'amount'           => $d['amount'],
                'action_date'      => $d['action_date'],
                'notes'            => $d['notes'] ?? null,
                'id'               => $id,
            ]
        )->rowCount() >= 0;
    }

    public function findFull(int $id): ?array
    {
        $row = $this->query("SELECT * FROM loan_register_view WHERE id = :id", ['id' => $id])->fetch();
        return $row ?: null;
    }

    // -------------------------------------------------------------
    // Register listing (server-side, used by DataTables + Reports + Export)
    // Builds WHERE clause from an associative $filters array.
    // -------------------------------------------------------------
    public function buildFilterClause(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(name ILIKE :search OR surname ILIKE :search OR id_number ILIKE :search
                         OR account_number ILIKE :search OR reference_number ILIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['branch_id'])) {
            $where[] = "branch_id = :branch_id";
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['loan_group'])) {
            $where[] = "loan_group = :loan_group";
            $params['loan_group'] = $filters['loan_group'];
        }
        if (!empty($filters['loan_status_id'])) {
            $where[] = "loan_status_id = :loan_status_id";
            $params['loan_status_id'] = $filters['loan_status_id'];
        }
        if (!empty($filters['report_status_id'])) {
            $where[] = "report_status_id = :report_status_id";
            $params['report_status_id'] = $filters['report_status_id'];
        }
        if (!empty($filters['date_loaded_from'])) {
            $where[] = "date_loaded::date >= :date_loaded_from";
            $params['date_loaded_from'] = $filters['date_loaded_from'];
        }
        if (!empty($filters['date_loaded_to'])) {
            $where[] = "date_loaded::date <= :date_loaded_to";
            $params['date_loaded_to'] = $filters['date_loaded_to'];
        }
        if (!empty($filters['action_date_from'])) {
            $where[] = "action_date >= :action_date_from";
            $params['action_date_from'] = $filters['action_date_from'];
        }
        if (!empty($filters['action_date_to'])) {
            $where[] = "action_date <= :action_date_to";
            $params['action_date_to'] = $filters['action_date_to'];
        }
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $where[] = "amount >= :amount_min";
            $params['amount_min'] = $filters['amount_min'];
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $where[] = "amount <= :amount_max";
            $params['amount_max'] = $filters['amount_max'];
        }
        if (isset($filters['loan_count_min']) && $filters['loan_count_min'] !== '') {
            $where[] = "loan_count >= :loan_count_min";
            $params['loan_count_min'] = $filters['loan_count_min'];
        }
        if (isset($filters['loan_count_max']) && $filters['loan_count_max'] !== '') {
            $where[] = "loan_count <= :loan_count_max";
            $params['loan_count_max'] = $filters['loan_count_max'];
        }
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $placeholders = [];
            foreach ($filters['ids'] as $i => $val) {
                $key = "id_{$i}";
                $placeholders[] = ":$key";
                $params[$key] = $val;
            }
            $where[] = "id IN (" . implode(',', $placeholders) . ")";
        }

        $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$sql, $params];
    }

    private const SORTABLE = [
        'reference_number', 'name', 'surname', 'id_number', 'account_number', 'amount',
        'branch_name', 'loan_count', 'loan_group', 'status', 'report_status', 'action_date', 'date_loaded',
    ];

    public function registerList(array $filters, string $orderBy = 'date_loaded', string $orderDir = 'DESC', int $limit = 25, int $offset = 0): array
    {
        [$whereSql, $params] = $this->buildFilterClause($filters);
        $orderBy  = in_array($orderBy, self::SORTABLE, true) ? $orderBy : 'date_loaded';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $total = (int) $this->query("SELECT COUNT(*) c FROM loan_register_view {$whereSql}", $params)->fetch()['c'];

        $sql = "SELECT * FROM loan_register_view {$whereSql} ORDER BY {$orderBy} {$orderDir} LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(":$k", $v); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public function registerAll(array $filters): array
    {
        [$whereSql, $params] = $this->buildFilterClause($filters);
        return $this->query("SELECT * FROM loan_register_view {$whereSql} ORDER BY date_loaded DESC", $params)->fetchAll();
    }

    // -------------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------------
    public function bulkUpdateStatus(array $ids, int $statusId): int
    {
        return $this->bulkUpdate($ids, 'loan_status_id', $statusId);
    }

    public function bulkUpdateReportStatus(array $ids, int $statusId): int
    {
        return $this->bulkUpdate($ids, 'report_status_id', $statusId);
    }

    private function bulkUpdate(array $ids, string $column, int $value): int
    {
        if (empty($ids)) return 0;
        $placeholders = [];
        $params = ['value' => $value];
        foreach ($ids as $i => $id) {
            $key = "id_{$i}";
            $placeholders[] = ":$key";
            $params[$key] = $id;
        }
        $sql = "UPDATE loans SET {$column} = :value, updated_at = NOW() WHERE id IN (" . implode(',', $placeholders) . ")";
        return $this->query($sql, $params)->rowCount();
    }

    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) return 0;
        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = "id_{$i}";
            $placeholders[] = ":$key";
            $params[$key] = $id;
        }
        $sql = "DELETE FROM loans WHERE id IN (" . implode(',', $placeholders) . ")";
        return $this->query($sql, $params)->rowCount();
    }

    // -------------------------------------------------------------
    // Dashboard aggregates
    // -------------------------------------------------------------
    public function kpis(): array
    {
        $row = $this->query(
            "SELECT
                COUNT(*)                                                              AS total_loans,
                COALESCE(SUM(l.amount), 0)                                            AS total_amount,
                COUNT(*) FILTER (WHERE ls.status_name NOT IN ('Completed','Rejected')) AS active_loans,
                COUNT(*) FILTER (WHERE ls.status_name = 'Completed')                  AS completed_loans,
                COUNT(*) FILTER (WHERE ls.status_name = 'Rejected')                   AS rejected_loans,
                COUNT(*) FILTER (WHERE date_trunc('month', l.created_at) = date_trunc('month', CURRENT_DATE)) AS new_this_month
             FROM loans l JOIN loan_statuses ls ON ls.id = l.loan_status_id"
        )->fetch();
        return $row;
    }

    public function groupCounts(): array
    {
        return $this->query(
            "SELECT loan_group, COUNT(*) AS total FROM loan_register_view GROUP BY loan_group"
        )->fetchAll();
    }

    public function loansByBranch(): array
    {
        return $this->query(
            "SELECT b.branch_name, COUNT(l.id) AS total, COALESCE(SUM(l.amount),0) AS total_amount
             FROM branches b LEFT JOIN loans l ON l.branch_id = b.id
             WHERE b.status = 'Active'
             GROUP BY b.branch_name ORDER BY b.branch_name"
        )->fetchAll();
    }

    public function loansByMonth(int $months = 12): array
    {
        return $this->query(
            "SELECT to_char(date_trunc('month', created_at), 'YYYY-MM') AS ym,
                    to_char(date_trunc('month', created_at), 'Mon') AS label,
                    COUNT(*) AS total
             FROM loans
             WHERE created_at >= date_trunc('month', CURRENT_DATE) - (:months || ' months')::interval
             GROUP BY 1, 2 ORDER BY 1",
            ['months' => $months]
        )->fetchAll();
    }

    // public function recentActivity(int $limit = 8): array
    // {
    //     return $this->query(
    //         "SELECT reference_number, name, surname, status, amount, date_loaded
    //          FROM loan_register_view ORDER BY date_loaded DESC LIMIT :limit"
    //     )->fetchAll();
    // }
public function recentActivity(int $limit = 8): array
{
    return $this->query(
        "SELECT reference_number, name, surname, status, amount, date_loaded
         FROM loan_register_view
         ORDER BY date_loaded DESC
         LIMIT :limit",
        ['limit' => $limit]
    )->fetchAll();
}
    // -------------------------------------------------------------
    // Reports screen
    // -------------------------------------------------------------
    public function reportSummary(array $filters): array
    {
        [$whereSql, $params] = $this->buildFilterClause($filters);
        $totals = $this->query(
            "SELECT COUNT(*) AS total_loans, COALESCE(SUM(amount),0) AS total_amount,
                    COUNT(*) FILTER (WHERE status NOT IN ('Completed','Rejected')) AS active_loans,
                    COUNT(*) FILTER (WHERE status = 'Completed') AS paid_loans,
                    COUNT(*) FILTER (WHERE status = 'Rejected') AS overdue_loans
             FROM loan_register_view {$whereSql}", $params
        )->fetch();

        $byBranch = $this->query(
            "SELECT branch_name,
                    COUNT(*) AS total_loans,
                    COALESCE(SUM(amount),0) AS total_amount,
                    COUNT(*) FILTER (WHERE status NOT IN ('Completed','Rejected')) AS active_loans,
                    COUNT(*) FILTER (WHERE status = 'Completed') AS paid_loans,
                    COUNT(*) FILTER (WHERE status = 'Rejected') AS overdue_loans
             FROM loan_register_view {$whereSql}
             GROUP BY branch_name ORDER BY branch_name", $params
        )->fetchAll();

        return ['totals' => $totals, 'by_branch' => $byBranch];
    }
}
