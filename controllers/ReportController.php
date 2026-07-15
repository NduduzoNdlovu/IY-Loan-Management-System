<?php

class ReportController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->view('reports/index', [
            'branches'       => (new Branch())->activeBranches(),
            'loanStatuses'   => (new LoanStatus())->all('id ASC'),
            'reportStatuses' => (new ReportStatus())->all('id ASC'),
            'csrf'           => $this->csrfToken(),
        ]);
    }

    private function filtersFromRequest(): array
    {
        return [
            'branch_id'        => $this->input('branch_id', ''),
            'loan_group'       => $this->input('loan_group', ''),
            'loan_status_id'   => $this->input('loan_status_id', ''),
            'report_status_id' => $this->input('report_status_id', ''),
            'date_loaded_from' => $this->input('date_from', ''),
            'date_loaded_to'   => $this->input('date_to', ''),
        ];
    }

    // AJAX: GET /reports/generate
    public function generate(): void
    {
        Auth::requireLogin();
        $summary = (new Loan())->reportSummary($this->filtersFromRequest());
        $this->json(['success' => true] + $summary);
    }
}
