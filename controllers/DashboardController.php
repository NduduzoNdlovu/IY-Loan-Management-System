<?php

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $loan = new Loan();

        $kpis        = $loan->kpis();
        $groupCounts = $loan->groupCounts();
        $byBranch    = $loan->loansByBranch();
        $byMonth     = $loan->loansByMonth();
        $recent      = $loan->recentActivity();

        $groups = ['Group 1' => 0, 'Group 2' => 0, 'Group 3' => 0];
        foreach ($groupCounts as $g) { $groups[$g['loan_group']] = (int) $g['total']; }

        $this->view('dashboard/index', [
            'kpis'     => $kpis,
            'groups'   => $groups,
            'byBranch' => $byBranch,
            'byMonth'  => $byMonth,
            'recent'   => $recent,
        ]);
    }
}
