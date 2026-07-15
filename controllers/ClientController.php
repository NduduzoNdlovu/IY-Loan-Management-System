<?php

class ClientController extends Controller
{
    // AJAX: GET /clients/lookup?id_number=xxxx
    public function lookup(): void
    {
        Auth::requireLogin();
        $idNumber = trim((string) $this->input('id_number', ''));
        if ($idNumber === '') {
            $this->json(['found' => false]);
        }

        $clientModel = new Client();
        $client = $clientModel->findByIdNumber($idNumber);

        if (!$client) {
            $this->json(['found' => false]);
        }

        $loanCount = $clientModel->loanCount($client['id']);
        $this->json([
            'found'  => true,
            'client' => [
                'id'             => $client['id'],
                'name'           => $client['name'],
                'surname'        => $client['surname'],
                'id_number'      => $client['id_number'],
                'account_number' => $client['account_number'],
                'phone'          => $client['phone'],
            ],
            'previous_loan_count' => $loanCount,
            'next_loan_count'     => $loanCount + 1,
            'group'               => Client::groupForCount($loanCount + 1),
        ]);
    }
}
