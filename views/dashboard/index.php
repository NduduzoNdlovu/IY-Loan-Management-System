<?php $pageTitle = 'Dashboard'; ?>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon bg-blue-soft text-blue"><i class="bi bi-people-fill"></i></div>
        <div>
            <div class="kpi-label">Total Loans</div>
            <div class="kpi-value"><?= number_format((int)($kpis['total_loans'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon bg-green-soft text-green"><i class="bi bi-box-seam-fill"></i></div>
        <div>
            <div class="kpi-label">Total Amount</div>
            <div class="kpi-value">R<?= number_format((float)($kpis['total_amount'] ?? 0), 2) ?></div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon bg-orange-soft text-orange"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <div class="kpi-label">Active Loans</div>
            <div class="kpi-value"><?= number_format((int)($kpis['active_loans'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon bg-red-soft text-red"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div>
            <div class="kpi-label">Rejected Loans</div>
            <div class="kpi-value"><?= number_format((int)($kpis['rejected_loans'] ?? 0)) ?></div>
        </div>
    </div>
</div>

<div class="kpi-grid kpi-grid-secondary">
    <a href="<?= APP_URL ?>/loans/register?loan_group=Group+1" class="kpi-card kpi-card-link">
        <div>
            <div class="kpi-label">Group 1 (1 - 3 Loans)</div>
            <div class="kpi-value"><?= number_format($groups['Group 1']) ?></div>
        </div>
    </a>
    <a href="<?= APP_URL ?>/loans/register?loan_group=Group+2" class="kpi-card kpi-card-link">
        <div>
            <div class="kpi-label">Group 2 (4 - 8 Loans)</div>
            <div class="kpi-value"><?= number_format($groups['Group 2']) ?></div>
        </div>
    </a>
    <a href="<?= APP_URL ?>/loans/register?loan_group=Group+3" class="kpi-card kpi-card-link">
        <div>
            <div class="kpi-label">Group 3 (9+ Loans)</div>
            <div class="kpi-value"><?= number_format($groups['Group 3']) ?></div>
        </div>
    </a>
    <div class="kpi-card">
        <div>
            <div class="kpi-label">New Loans (This Month)</div>
            <div class="kpi-value"><?= number_format((int)($kpis['new_this_month'] ?? 0)) ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="panel-card">
            <h6 class="panel-title">Loans per Branch</h6>
            <canvas id="branchChart" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card">
            <h6 class="panel-title">Loans per Month (This Year)</h6>
            <canvas id="monthChart" height="220"></canvas>
        </div>
    </div>
</div>

<div class="panel-card mt-3">
    <h6 class="panel-title">Recent Activity</h6>
    <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
            <thead>
                <tr>
                    <th>Reference</th><th>Name</th><th>Status</th><th>Amount</th><th>Date Loaded</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['reference_number']) ?></td>
                    <td><?= htmlspecialchars($r['name'] . ' ' . $r['surname']) ?></td>
                    <td><span class="badge-status status-<?= strtolower(str_replace(' ','-',$r['status'])) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td>R<?= number_format((float)$r['amount'], 2) ?></td>
                    <td><?= date('d M Y', strtotime($r['date_loaded'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No loans captured yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$pageScripts = '<script>
const branchLabels = ' . json_encode(array_column($byBranch, 'branch_name')) . ';
const branchData   = ' . json_encode(array_map('intval', array_column($byBranch, 'total'))) . ';
const monthLabels  = ' . json_encode(array_column($byMonth, 'label')) . ';
const monthData    = ' . json_encode(array_map('intval', array_column($byMonth, 'total'))) . ';
</script>
<script src="' . APP_URL . '/assets/js/dashboard.js"></script>';
?>
