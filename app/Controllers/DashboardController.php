<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ChiSoDienRepository;
use App\Repositories\BlockchainBatchRepository;

class DashboardController
{
    private ChiSoDienRepository $chisodienRepository;
    private BlockchainBatchRepository $batchRepository;

    public function __construct(ChiSoDienRepository $chisodienRepository, BlockchainBatchRepository $batchRepository)
    {
        $this->chisodienRepository = $chisodienRepository;
        $this->batchRepository = $batchRepository;
    }

    public function summary(Request $request): void
    {
        $months = $this->chisodienRepository->getAllMonths();
        $latestBatch = $this->batchRepository->getLastBatch();

        Response::json([
            'success' => true,
            'months' => $months,
            'blockchain_summary' => [
                'total_batches' => $latestBatch ? intval($latestBatch->blockId) : 0,
                'last_block' => $latestBatch ? $latestBatch->toArray() : null,
            ],
        ]);
    }
}
