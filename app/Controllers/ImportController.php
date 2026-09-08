<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ImportService;

class ImportController
{
    private ImportService $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    public function import(Request $request): void
    {
        if ($request->method() !== 'POST') {
            Response::json(['success' => false, 'error' => 'Phải dùng phương thức POST để import dữ liệu.'], 405);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');

        $file = $request->file('import_file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'error' => 'Vui lòng tải lên tệp CSV, SQL hoặc XLSX hợp lệ.'], 400);
        }

        try {
            $result = $this->importService->importFile($file['tmp_name'], $file['name']);
            Response::json($result);
        } catch (\Throwable $exception) {
            Response::json(['success' => false, 'error' => $exception->getMessage()], 500);
        }
    }
}
