<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\BlockchainService;
use App\Services\VerificationService;

class BlockchainController
{
    private BlockchainService $blockchainService;
    private VerificationService $verificationService;

    public function __construct(BlockchainService $blockchainService, VerificationService $verificationService)
    {
        $this->blockchainService = $blockchainService;
        $this->verificationService = $verificationService;
    }

    public function createBatch(Request $request): void
    {
        if ($request->method() !== 'POST') {
            Response::json(['success' => false, 'error' => 'Phải dùng phương thức POST để tạo batch blockchain.'], 405);
        }

        $month = intval($request->input('thang'));
        $year = intval($request->input('nam'));
        $signer = trim($request->input('signer', 'admin'));

        if ($month < 1 || $month > 12 || $year < 2000) {
            Response::json(['success' => false, 'error' => 'Thông tin tháng hoặc năm không hợp lệ.'], 400);
        }

        try {
            $batches = $this->blockchainService->createMonthlyBatches($month, $year, 1000, $signer);
            Response::json(['success' => true, 'batches' => array_map(fn($batch) => $batch->toArray(), $batches)]);
        } catch (\Throwable $exception) {
            Response::json(['success' => false, 'error' => $exception->getMessage()], 500);
        }
    }

    public function verifyBatch(Request $request): void
    {
        $month = intval($request->input('thang'));
        $year = intval($request->input('nam'));

        if ($month < 1 || $month > 12 || $year < 2000) {
            Response::json(['success' => false, 'error' => 'Thông tin tháng hoặc năm không hợp lệ.'], 400);
        }

        $result = $this->verificationService->verifyBatch($month, $year);
        Response::json($result);
    }

    public function verifyChain(): void
    {
        $result = $this->verificationService->verifyChain();
        Response::json($result);
    }
}
