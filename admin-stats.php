<?php
require_once __DIR__ . '/require-admin.php';
// Start output buffering immediately to capture any unexpected output/warnings
if (function_exists('ob_start')) ob_start();
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
// Prevent warnings from being printed into JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
// Start output buffering so we can discard any unexpected output (warnings) before sending JSON
if (function_exists('ob_start')) ob_start();

function send_json($data) {
    // Clear any output buffers (including warnings captured) to avoid corrupting JSON
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode($data);
}

function householdMapCacheFile($area = '') {
    $key = md5('household_map_v3|' . mb_strtolower(trim((string)$area), 'UTF-8'));
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'quanlytiendien_household_map_' . $key . '.json';
}

function readHouseholdMapCache($area = '') {
    $cacheFile = householdMapCacheFile($area);
    if (!is_file($cacheFile)) {
        return null;
    }
    $age = time() - filemtime($cacheFile);
    if ($age > 60) {
        @unlink($cacheFile);
        return null;
    }
    $content = @file_get_contents($cacheFile);
    if ($content === false || trim($content) === '') {
        return null;
    }
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : null;
}

function writeHouseholdMapCache($data, $area = '') {
    $cacheFile = householdMapCacheFile($area);
    $tempFile = $cacheFile . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }
    @file_put_contents($tempFile, $json);
    @rename($tempFile, $cacheFile);
}

function normalizeDateParam($value, $isStart = true) {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}$/', $value)) {
        return $isStart ? "$value-01-01 00:00:00" : "$value-12-31 23:59:59";
    }

    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        [$year, $month] = explode('-', $value);
        $month = intval($month);
        if ($month >= 1 && $month <= 12) {
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);
            $day = $isStart ? '01' : date('t', strtotime("$year-$month-01"));
            $time = $isStart ? '00:00:00' : '23:59:59';
            return "$year-$month-$day $time";
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $isStart ? "$value 00:00:00" : "$value 23:59:59";
    }

    return '';
}

function normalizeProvinceName($value) {
    if ($value === null) {
        return '';
    }
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/\b(tp|thanh pho|thành phố|tp\.|tp\b)\b/u', '', $value);
    // Remove Vietnamese diacritics
    $search = array('à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ằ', 'ắ', 'ẳ', 'ẵ', 'ặ', 'â', 'ầ', 'ấ', 'ẩ', 'ẫ', 'ậ',
                    'è', 'é', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ề', 'ế', 'ể', 'ễ', 'ệ',
                    'ì', 'í', 'ỉ', 'ĩ', 'ị',
                    'ò', 'ó', 'ỏ', 'õ', 'ọ', 'ô', 'ồ', 'ố', 'ổ', 'ỗ', 'ộ', 'ơ', 'ờ', 'ớ', 'ở', 'ỡ', 'ợ',
                    'ù', 'ú', 'ủ', 'ũ', 'ụ', 'ư', 'ừ', 'ứ', 'ử', 'ữ', 'ự',
                    'ỳ', 'ý', 'ỷ', 'ỹ', 'ỵ',
                    'đ',
                    'đ');
    $replace = array('a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
                     'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
                     'i', 'i', 'i', 'i', 'i',
                     'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
                     'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
                     'y', 'y', 'y', 'y', 'y',
                     'd',
                     'd');
    $value = str_replace($search, $replace, $value);
    $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function canonicalProvinceName($value) {
    $normalized = normalizeProvinceName($value);
    if ($normalized === '') {
        return '';
    }

    $mergedProvinceAliases = [
        'lao cai' => ['lao cai', 'yen bai'],
        'tuyen quang' => ['tuyen quang', 'ha giang'],
        'thai nguyen' => ['thai nguyen', 'bac kan'],
        'phu tho' => ['phu tho', 'vinh phuc', 'hoa binh'],
        'bac ninh' => ['bac ninh', 'bac giang'],
        'hai phong' => ['hai phong', 'hai duong'],
        'hung yen' => ['hung yen', 'thai binh'],
        'ninh binh' => ['ninh binh', 'ha nam', 'nam dinh'],
        'quang tri' => ['quang tri', 'quang binh'],
        'hue' => ['hue', 'thua thien hue'],
        'da nang' => ['da nang', 'quang nam'],
        'quang ngai' => ['quang ngai', 'kon tum'],
        'gia lai' => ['gia lai', 'binh dinh'],
        'dak lak' => ['dak lak', 'phu yen'],
        'khanh hoa' => ['khanh hoa', 'ninh thuan'],
        'lam dong' => ['lam dong', 'dak nong', 'binh thuan'],
        'dong nai' => ['dong nai', 'binh phuoc'],
        'ho chi minh' => ['ho chi minh', 'ho chi minh city', 'binh duong', 'ba ria vung tau'],
        'tay ninh' => ['tay ninh', 'long an'],
        'dong thap' => ['dong thap', 'tien giang'],
        'vinh long' => ['vinh long', 'ben tre', 'tra vinh'],
        'can tho' => ['can tho', 'soc trang', 'hau giang'],
        'an giang' => ['an giang', 'kien giang'],
        'ca mau' => ['ca mau', 'bac lieu']
    ];

    foreach ($mergedProvinceAliases as $canonical => $aliases) {
        if (in_array($normalized, $aliases, true)) {
            return $canonical;
        }
    }

    return $normalized;
}

function extractProvinceFromAddress($address) {
    if (!$address) {
        return '';
    }
    $parts = explode(',', $address);
    $lastPart = trim(end($parts));
    if (!$lastPart && count($parts) > 1) {
        $lastPart = trim($parts[count($parts) - 2]);
    }
    if (!$lastPart) {
        return '';
    }
    $clean = preg_replace('/\b(tp|thanh pho|thành phố|tp\.|tp\b)\b/u', '', mb_strtolower($lastPart, 'UTF-8'));
    $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);
    $clean = preg_replace('/\s+/u', ' ', $clean);
    $clean = trim($clean);
    if ($clean === '') {
        return trim($lastPart);
    }
    return mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
}

function extractDistrictFromAddress($address) {
    if (!$address) {
        return '';
    }
    $parts = array_map('trim', explode(',', $address));
    if (count($parts) >= 2) {
        $districtPart = $parts[count($parts) - 2];
        if ($districtPart !== '') {
            return $districtPart;
        }
    }
    return '';
}

function extractWardFromAddress($address) {
    if (!$address) {
        return '';
    }
    $parts = array_map('trim', explode(',', $address));
    if (count($parts) >= 3) {
        return $parts[count($parts) - 3];
    }
    return '';
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if (!$type) {
    send_json(['success' => false, 'error' => 'Thiếu loại thống kê.']);
    exit;
}

switch ($type) {
    case 'revenue':
        $from = isset($_GET['from']) ? normalizeDateParam($_GET['from'], true) : '';
        $to = isset($_GET['to']) ? normalizeDateParam($_GET['to'], false) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $includeList = isset($_GET['list']) && $_GET['list'] === '1';

        // Total query
        $params = [];
        $whereParts = [];
        $sql = "SELECT COALESCE(SUM(tongtien), 0) AS total FROM hoadon";
        if ($from !== '' && $to !== '') {
            $whereParts[] = "ngaytao BETWEEN ? AND ?";
            $params[] = $from;
            $params[] = $to;
        } elseif ($from !== '') {
            $whereParts[] = "ngaytao >= ?";
            $params[] = $from;
        } elseif ($to !== '') {
            $whereParts[] = "ngaytao <= ?";
            $params[] = $to;
        }
        if ($status !== '') {
            $whereParts[] = "trangthai = ?";
            $params[] = $status;
        }
        if ($whereParts) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }
        $stmt = $conn->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $total = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
        $stmt->close();

        // If requested, also return list of invoices with pagination support
        $invoices = [];
        $total_invoices = 0;
        if ($includeList) {
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
            if ($per_page <= 0) $per_page = 20;

            // Build where clause
            $where = '';
            $paramsList = [];
            if ($from !== '' && $to !== '') {
                $where = " WHERE ngaytao BETWEEN ? AND ?";
                $paramsList = [$from, $to];
            } elseif ($from !== '') {
                $where = " WHERE ngaytao >= ?";
                $paramsList = [$from];
            } elseif ($to !== '') {
                $where = " WHERE ngaytao <= ?";
                $paramsList = [$to];
            }
            if ($status !== '') {
                $where .= $paramsList ? ' AND trangthai = ?' : ' WHERE trangthai = ?';
                $paramsList[] = $status;
            }

            // Count total invoices matching filter
            $countSql = "SELECT COUNT(*) AS cnt FROM hoadon" . $where;
            $stmtCount = $conn->prepare($countSql);
            if ($paramsList) {
                if (count($paramsList) === 1) {
                    $stmtCount->bind_param('s', $paramsList[0]);
                } elseif (count($paramsList) === 2) {
                    $stmtCount->bind_param('ss', $paramsList[0], $paramsList[1]);
                } else {
                    $stmtCount->bind_param('sss', $paramsList[0], $paramsList[1], $paramsList[2]);
                }
            }
            $stmtCount->execute();
            $resCount = $stmtCount->get_result();
            $rc = $resCount->fetch_assoc();
            $total_invoices = intval($rc['cnt']);
            $stmtCount->close();

            $offset = ($page - 1) * $per_page;

            $sqlList = "SELECT maHD, maKH, tongtien, trangthai, ngaytao FROM hoadon" . $where . " ORDER BY ngaytao DESC, maHD DESC LIMIT ?, ?";
            $stmt2 = $conn->prepare($sqlList);
            if ($paramsList) {
                if (count($paramsList) === 1) {
                    $stmt2->bind_param('sii', $paramsList[0], $offset, $per_page);
                } elseif (count($paramsList) === 2) {
                    $stmt2->bind_param('ssii', $paramsList[0], $paramsList[1], $offset, $per_page);
                } else {
                    $stmt2->bind_param('sssii', $paramsList[0], $paramsList[1], $paramsList[2], $offset, $per_page);
                }
            } else {
                $stmt2->bind_param('ii', $offset, $per_page);
            }
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($r = $res2->fetch_assoc()) {
                $invoices[] = [
                    'maHD' => intval($r['maHD']),
                    'maKH' => $r['maKH'],
                    'tongtien' => floatval($r['tongtien']),
                    'trangthai' => $r['trangthai'],
                    'ngaytao' => $r['ngaytao']
                ];
            }
            $stmt2->close();
        }

        send_json(['success' => true, 'total' => $total, 'invoices' => $invoices, 'total_invoices' => $total_invoices]);
        break;

    case 'household':
        $area = isset($_GET['area']) ? trim($_GET['area']) : '';
        $includeList = isset($_GET['list']) && $_GET['list'] === '1';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        if ($per_page <= 0) $per_page = 20;
        if ($per_page > 100) $per_page = 100;

        $where = '';
        $params = [];
        if ($area !== '') {
            $where = " WHERE LOWER(diachi) LIKE ?";
            $params[] = '%' . mb_strtolower($area, 'UTF-8') . '%';
        }

        $countSql = "SELECT COUNT(*) AS total FROM taikhoan" . $where;
        $stmtCount = $conn->prepare($countSql);
        if ($params) {
            $stmtCount->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmtCount->execute();
        $resultCount = $stmtCount->get_result();
        $rowCount = $resultCount->fetch_assoc();
        $total = intval($rowCount['total']);
        $stmtCount->close();

        $response = ['success' => true, 'total' => $total];

        if ($includeList) {
            $totalPages = $total > 0 ? intval(ceil($total / $per_page)) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $per_page;

            $sql = "SELECT maKH, hovaten, diachi, sodienthoai, quyen FROM taikhoan" . $where . " ORDER BY maKH ASC LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sql);
            if ($params) {
                $types = str_repeat('s', count($params)) . 'ii';
                $bindParams = array_merge([$types], $params, [$per_page, $offset]);
                $bindRefs = [];
                foreach ($bindParams as $key => $value) {
                    $bindRefs[$key] = &$bindParams[$key];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindRefs);
            } else {
                $stmt->bind_param('ii', $per_page, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $households = [];
            while ($r = $result->fetch_assoc()) {
                $households[] = [
                    'maKH' => $r['maKH'],
                    'name' => $r['hovaten'],
                    'address' => $r['diachi'],
                    'phone' => $r['sodienthoai'],
                    'role' => $r['quyen']
                ];
            }
            $stmt->close();

            $response['households'] = $households;
            $response['page'] = $page;
            $response['per_page'] = $per_page;
            $response['total_pages'] = $totalPages;
            $response['first_page'] = 1;
            $response['last_page'] = $totalPages;
        }

        send_json($response);
        break;

    case 'household_map':
        $area = isset($_GET['area']) ? trim($_GET['area']) : '';
        $cacheKey = $area;
        $cached = readHouseholdMapCache($cacheKey);
        if (is_array($cached)) {
            send_json($cached);
            break;
        }

        $selectedProvinceKey = canonicalProvinceName($area);
        $provinceTotals = [];
        $provinceDistricts = [];
        $districtTotals = [];
        $provinceWards = [];
        $wardTotals = [];
        $wardCodeTotals = [];
        $supportedProvinceKeys = [
            'ha noi', 'cao bang', 'tuyen quang', 'dien bien', 'lai chau', 'son la',
            'lao cai', 'thai nguyen', 'lang son', 'quang ninh', 'bac ninh', 'phu tho',
            'hai phong', 'hung yen', 'ninh binh', 'thanh hoa', 'nghe an', 'ha tinh',
            'quang tri', 'hue', 'da nang', 'quang ngai', 'gia lai', 'khanh hoa',
            'dak lak', 'lam dong', 'dong nai', 'ho chi minh', 'tay ninh', 'dong thap',
            'vinh long', 'an giang', 'can tho', 'ca mau'
        ];
        $focusProvince = in_array($selectedProvinceKey, $supportedProvinceKeys, true)
            ? $selectedProvinceKey
            : '';

        $query = "SELECT maKH, diachi FROM taikhoan WHERE diachi IS NOT NULL AND diachi != ''";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $address = trim($row['diachi'] ?? '');
                $customerCode = strtoupper(trim($row['maKH'] ?? ''));
                $province = canonicalProvinceName(extractProvinceFromAddress($address));
                if ($province === '') {
                    $province = 'Không xác định';
                }
                if (!isset($provinceTotals[$province])) {
                    $provinceTotals[$province] = 0;
                }
                $provinceTotals[$province]++;

                $district = extractDistrictFromAddress($address);
                if ($district === '') {
                    $district = 'Không xác định';
                }
                if (!isset($provinceDistricts[$province])) {
                    $provinceDistricts[$province] = [];
                }
                if (!isset($provinceDistricts[$province][$district])) {
                    $provinceDistricts[$province][$district] = 0;
                }
                $provinceDistricts[$province][$district]++;

                $ward = extractWardFromAddress($address);
                if ($ward !== '') {
                    if (!isset($provinceWards[$province][$ward])) {
                        $provinceWards[$province][$ward] = 0;
                    }
                    $provinceWards[$province][$ward]++;
                }

                // Customer IDs contain the electric code + province(2) + ward(5) + sequence(6).
                if (preg_match('/^[A-Z]{2}\d{13}$/', $customerCode)) {
                    $wardCode = substr($customerCode, 4, 5);
                    if (!isset($wardCodeTotals[$wardCode])) {
                        $wardCodeTotals[$wardCode] = 0;
                    }
                    $wardCodeTotals[$wardCode]++;
                }

                if ($selectedProvinceKey !== '' && canonicalProvinceName($province) === $selectedProvinceKey) {
                    if (!isset($districtTotals[$district])) {
                        $districtTotals[$district] = 0;
                    }
                    $districtTotals[$district]++;
                    if ($ward !== '') {
                        if (!isset($wardTotals[$ward])) {
                            $wardTotals[$ward] = 0;
                        }
                        $wardTotals[$ward]++;
                    }
                }
            }
        }

        arsort($provinceTotals);
        $provinceData = [];
        foreach ($provinceTotals as $provinceName => $count) {
            $provinceData[] = ['province' => $provinceName, 'total' => intval($count)];
        }

        $provinceDistrictTotals = [];
        foreach ($provinceDistricts as $provinceName => $districts) {
            arsort($districts);
            $districtDataByProvince = [];
            foreach ($districts as $districtName => $count) {
                $districtDataByProvince[] = ['district' => $districtName, 'total' => intval($count)];
            }
            $provinceDistrictTotals[] = [
                'province' => $provinceName,
                'district_totals' => $districtDataByProvince
            ];
        }

        arsort($wardTotals);
        $selectedWardData = [];
        foreach ($wardTotals as $wardName => $count) {
            $selectedWardData[] = ['ward' => $wardName, 'total' => intval($count)];
        }

        arsort($districtTotals);
        $selectedDistrictData = [];
        foreach ($districtTotals as $districtName => $count) {
            $selectedDistrictData[] = ['district' => $districtName, 'total' => intval($count)];
        }

        $provinceTotal = 0;
        if ($focusProvince && isset($provinceTotals[$focusProvince])) {
            $provinceTotal = $provinceTotals[$focusProvince];
        }

        $payload = [
            'success' => true,
            'selected_province' => $focusProvince ?: null,
            'focus_province' => $focusProvince ?: null,
            'province_total' => intval($provinceTotal),
            'province_totals' => $provinceData,
            'province_district_totals' => $provinceDistrictTotals,
            'district_totals' => $selectedDistrictData,
            'ward_totals' => $selectedWardData,
            'ward_code_totals' => $wardCodeTotals
        ];

        writeHouseholdMapCache($payload, $cacheKey);
        send_json($payload);
        break;

    case 'analytics':
        $months = isset($_GET['months']) ? max(1, min(24, intval($_GET['months']))) : 12;

        $summary = [];
        $result = $conn->query("SELECT COUNT(*) as cnt FROM taikhoan WHERE quyen = 'khachhang'");
        $row = $result ? $result->fetch_assoc() : null;
        $summary['total_customers'] = ($row && isset($row['cnt'])) ? intval($row['cnt']) : 0;

        $result = $conn->query("SELECT COUNT(*) as cnt FROM chisodien");
        $row = $result ? $result->fetch_assoc() : null;
        $summary['total_readings'] = ($row && isset($row['cnt'])) ? intval($row['cnt']) : 0;

        $result = $conn->query("SELECT COALESCE(AVG(chisomoi - chisocu), 0) as avg_kwh FROM chisodien WHERE ngaynhap >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
        $row = $result ? $result->fetch_assoc() : null;
        $summary['avg_consumption_last_month'] = ($row && isset($row['avg_kwh'])) ? floatval($row['avg_kwh']) : 0;

        $result = $conn->query("SELECT COALESCE(SUM(tongtien), 0) as total_revenue FROM hoadon WHERE YEAR(ngaytao) = YEAR(CURDATE())");
        $row = $result ? $result->fetch_assoc() : null;
        $summary['revenue_this_year'] = ($row && isset($row['total_revenue'])) ? floatval($row['total_revenue']) : 0;

        $result = $conn->query("SELECT COUNT(*) as cnt FROM taikhoan t WHERE quyen = 'khachhang' AND t.maKH NOT IN (SELECT DISTINCT maKH FROM chisodien WHERE MONTH(ngaynhap) = MONTH(CURDATE()) AND YEAR(ngaynhap) = YEAR(CURDATE()))");
        $row = $result ? $result->fetch_assoc() : null;
        $summary['missing_readings_current_month'] = ($row && isset($row['cnt'])) ? intval($row['cnt']) : 0;

        $consumption_trend = [];
        $trendSql = "SELECT DATE_FORMAT(ngaynhap, '%Y-%m') as month, COALESCE(SUM(chisomoi - chisocu), 0) as total_kwh FROM chisodien WHERE ngaynhap >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) GROUP BY month ORDER BY month ASC";
        $stmtTrend = $conn->prepare($trendSql);
        if ($stmtTrend) {
            $stmtTrend->bind_param('i', $months);
            $stmtTrend->execute();
            $resultTrend = $stmtTrend->get_result();
            while ($row = $resultTrend->fetch_assoc()) {
                $consumption_trend[] = [
                    'month' => $row['month'],
                    'total_kwh' => floatval($row['total_kwh'])
                ];
            }
            $stmtTrend->close();
        }

        $top_consumers = [];
        $topSql = "SELECT t.maKH, t.hovaten, COALESCE(SUM(c.chisomoi - c.chisocu), 0) AS total_kwh FROM taikhoan t LEFT JOIN chisodien c ON t.maKH = c.maKH AND c.ngaynhap >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) WHERE t.quyen = 'khachhang' GROUP BY t.maKH, t.hovaten ORDER BY total_kwh DESC LIMIT 5";
        $stmtTop = $conn->prepare($topSql);
        if ($stmtTop) {
            $stmtTop->bind_param('i', $months);
            $stmtTop->execute();
            $resultTop = $stmtTop->get_result();
            while ($row = $resultTop->fetch_assoc()) {
                $top_consumers[] = [
                    'maKH' => $row['maKH'],
                    'hovaten' => $row['hovaten'],
                    'total_kwh' => floatval($row['total_kwh'])
                ];
            }
            $stmtTop->close();
        }

        $segments = [];
        $segmentSql = "SELECT segment, COUNT(*) AS customer_count, ROUND(AVG(avg_kwh), 2) AS avg_kwh FROM (SELECT maKH, AVG(chisomoi - chisocu) AS avg_kwh, CASE WHEN AVG(chisomoi - chisocu) < 50 THEN 'Thấp (<50 kWh)' WHEN AVG(chisomoi - chisocu) < 100 THEN 'Trung bình (50-100 kWh)' WHEN AVG(chisomoi - chisocu) < 200 THEN 'Cao (100-200 kWh)' ELSE 'Rất cao (>200 kWh)' END AS segment FROM chisodien WHERE ngaynhap >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY maKH) t GROUP BY segment ORDER BY FIELD(segment, 'Thấp (<50 kWh)', 'Trung bình (50-100 kWh)', 'Cao (100-200 kWh)', 'Rất cao (>200 kWh)')";
        $result = $conn->query($segmentSql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $segments[] = [
                    'segment' => $row['segment'],
                    'customer_count' => intval($row['customer_count']),
                    'avg_kwh' => floatval($row['avg_kwh'])
                ];
            }
        }

        $revenue_trend = [];
        $revenueSql = "SELECT DATE_FORMAT(ngaytao, '%Y-%m') AS month, COALESCE(SUM(tongtien), 0) AS total_revenue FROM hoadon WHERE ngaytao >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) GROUP BY month ORDER BY month ASC";
        $stmtRevenue = $conn->prepare($revenueSql);
        if ($stmtRevenue) {
            $stmtRevenue->bind_param('i', $months);
            $stmtRevenue->execute();
            $resultRevenue = $stmtRevenue->get_result();
            while ($row = $resultRevenue->fetch_assoc()) {
                $revenue_trend[] = [
                    'month' => $row['month'],
                    'total_revenue' => floatval($row['total_revenue'])
                ];
            }
            $stmtRevenue->close();
        }

        send_json([
            'success' => true,
            'summary' => $summary,
            'consumption_trend' => $consumption_trend,
            'revenue_trend' => $revenue_trend,
            'top_consumers' => $top_consumers,
            'segments' => $segments
        ]);
        break;

    case 'anomalous_customers':
        // Server-side anomaly detection per customer over a rolling window
        $months = isset($_GET['months']) ? max(3, intval($_GET['months'])) : 6; // lookback window
        $zThreshold = isset($_GET['z']) ? floatval($_GET['z']) : 2.5; // z-score threshold
        $topN = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;

        $sql = "SELECT t.maKH, t.hovaten, DATE_FORMAT(c.ngaynhap, '%Y-%m') AS ym, COALESCE(SUM(c.chisomoi - c.chisocu),0) AS kwh FROM chisodien c JOIN taikhoan t ON t.maKH = c.maKH WHERE c.ngaynhap >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND t.quyen = 'khachhang' GROUP BY t.maKH, ym ORDER BY t.maKH, ym ASC";
        $stmt = $conn->prepare($sql);
        $anomalies = [];
        if ($stmt) {
            $stmt->bind_param('i', $months);
            $stmt->execute();
            $res = $stmt->get_result();
            $customers = [];
            while ($r = $res->fetch_assoc()) {
                $kid = $r['maKH'];
                if (!isset($customers[$kid])) $customers[$kid] = ['maKH' => $kid, 'hovaten' => $r['hovaten'], 'series' => []];
                $customers[$kid]['series'][$r['ym']] = floatval($r['kwh']);
            }
            $stmt->close();

            foreach ($customers as $cust) {
                $series = array_values($cust['series']);
                if (count($series) < 2) continue;
                $count = count($series);
                $mean = array_sum($series) / $count;
                $variance = 0.0;
                foreach ($series as $v) $variance += pow($v - $mean, 2);
                $variance = $variance / $count;
                $std = sqrt($variance);
                $last = $series[$count - 1];
                $prev = $series[$count - 2];
                $pctChange = ($prev == 0) ? ($last > 0 ? 100.0 : 0.0) : (($last - $prev) / $prev) * 100.0;
                $zscore = ($std > 0) ? (($last - $mean) / $std) : 0.0;

                // Criteria: high z-score OR very large percent change
                if (abs($zscore) >= $zThreshold || abs($pctChange) >= 200) {
                    $anomalies[] = [
                        'maKH' => $cust['maKH'],
                        'hovaten' => $cust['hovaten'],
                        'last_kwh' => round($last,2),
                        'mean_kwh' => round($mean,2),
                        'std_kwh' => round($std,2),
                        'zscore' => round($zscore,2),
                        'pct_change' => round($pctChange,1),
                        'months_count' => $count
                    ];
                }
            }

            // sort by absolute zscore desc then pct_change
            usort($anomalies, function($a, $b){
                $ka = abs($a['zscore']) + abs($a['pct_change'])/100.0;
                $kb = abs($b['zscore']) + abs($b['pct_change'])/100.0;
                if ($ka == $kb) return 0;
                return ($ka > $kb) ? -1 : 1;
            });
            $anomalies = array_slice($anomalies, 0, $topN);
        }

        send_json(['success' => true, 'anomalies' => $anomalies, 'params' => ['months' => $months, 'z' => $zThreshold, 'limit' => $topN]]);
        break;

        case 'revenue_trend_range':
            // Compare the selected period against the same relative period in all years present in DB.
            $fromRaw = isset($_GET['from']) ? $_GET['from'] : '';
            $toRaw = isset($_GET['to']) ? $_GET['to'] : '';
            $from = normalizeDateParam($fromRaw, true);
            $to = normalizeDateParam($toRaw, false);

            $labels = [];
            $series = [];

            if ($from !== '' && $to !== '') {
                try {
                    $startDT = new DateTime($from);
                    $endDT = new DateTime($to);
                } catch (Exception $e) {
                    send_json(['success' => false, 'error' => 'Ngày không hợp lệ.']);
                    break;
                }

                // Calculate number of months in period (inclusive)
                $monthsCount = ($endDT->format('Y') - $startDT->format('Y')) * 12 + ($endDT->format('n') - $startDT->format('n')) + 1;
                if ($monthsCount < 1) $monthsCount = 1;

                // Build labels as month/year strings based on the selected period (relative positions)
                $labels = [];
                for ($i = 0; $i < $monthsCount; $i++) {
                    $d = (clone $startDT)->modify("+{$i} month");
                    $labels[] = $d->format('m/Y');
                }

                // Find year bounds in DB
                $yrRes = $conn->query("SELECT MIN(YEAR(ngaytao)) AS miny, MAX(YEAR(ngaytao)) AS maxy FROM hoadon");
                $yrRow = $yrRes ? $yrRes->fetch_assoc() : null;
                $miny = ($yrRow && isset($yrRow['miny'])) ? intval($yrRow['miny']) : intval($startDT->format('Y'));
                $maxy = ($yrRow && isset($yrRow['maxy'])) ? intval($yrRow['maxy']) : intval($endDT->format('Y'));
                if ($miny <= 0) $miny = intval($startDT->format('Y'));
                if ($maxy <= 0) $maxy = intval($endDT->format('Y'));

                // For each year in range, compute period aligned to that year and query totals per month
                for ($yr = $miny; $yr <= $maxy; $yr++) {
                    // Build start and end for this year's relative period
                    $periodStart = DateTime::createFromFormat('Y-n-j', sprintf('%04d-%d-%d', $yr, intval($startDT->format('n')), 1));
                    if (!$periodStart) continue;
                    $periodEnd = (clone $periodStart)->modify('+' . ($monthsCount - 1) . ' month')->modify('last day of this month');

                    $sql = "SELECT DATE_FORMAT(ngaytao, '%Y-%m') AS ym, COALESCE(SUM(tongtien),0) AS total_revenue FROM hoadon WHERE ngaytao BETWEEN ? AND ? GROUP BY ym ORDER BY ym ASC";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) continue;
                    $ps = $periodStart->format('Y-m-d 00:00:00');
                    $pe = $periodEnd->format('Y-m-d 23:59:59');
                    $stmt->bind_param('ss', $ps, $pe);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $map = [];
                    while ($r = $res->fetch_assoc()) {
                        $map[$r['ym']] = floatval($r['total_revenue']);
                    }
                    $stmt->close();

                    // Build data array aligned with labels using periodStart months
                    $data = [];
                    for ($i = 0; $i < $monthsCount; $i++) {
                        $d = (clone $periodStart)->modify("+{$i} month");
                        $ym = $d->format('Y-m');
                        $data[] = isset($map[$ym]) ? $map[$ym] : 0;
                    }

                    $series[] = ['year' => (string)$yr, 'data' => $data];
                }
            }

            send_json(['success' => true, 'labels' => $labels, 'series' => $series]);
            break;

    case 'consumption':
        $maKH = isset($_GET['maKH']) ? trim($_GET['maKH']) : '';
        $from = isset($_GET['from']) ? trim($_GET['from']) : '';
        $to = isset($_GET['to']) ? trim($_GET['to']) : '';
        $sql = "SELECT COALESCE(SUM(dntieuthu), 0) AS total FROM chisodien";
        $clauses = [];
        $params = [];

        if ($maKH !== '') {
            $clauses[] = 'maKH = ?';
            $params[] = $maKH;
        }
        if ($from && $to) {
            $clauses[] = 'DATE(ngaynhap) BETWEEN ? AND ?';
            $params[] = $from;
            $params[] = $to;
        }
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $stmt = $conn->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        send_json(['success' => true, 'total' => intval($row['total'])]);
        break;

    case 'consumption_trend':
        $maKH = isset($_GET['maKH']) ? trim($_GET['maKH']) : '';
        $fromRaw = isset($_GET['from']) ? $_GET['from'] : '';
        $toRaw = isset($_GET['to']) ? $_GET['to'] : '';
        $from = normalizeDateParam($fromRaw, true);
        $to = normalizeDateParam($toRaw, false);

        if ($from === '' || $to === '') {
            $to = date('Y-m-d 23:59:59');
            $from = date('Y-m-d 00:00:00', strtotime('-11 months', strtotime($to)));
        }

        try {
            $start = new DateTime($from);
            $end = new DateTime($to);
        } catch (Exception $e) {
            send_json(['success' => false, 'error' => 'Ngày không hợp lệ.']);
            break;
        }

        if ($start > $end) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }

        $labels = [];
        $monthsCount = ($end->format('Y') - $start->format('Y')) * 12 + ($end->format('n') - $start->format('n')) + 1;
        if ($monthsCount < 1) $monthsCount = 1;
        
        for ($i = 0; $i < $monthsCount; $i++) {
            $d = (clone $start)->modify("+{$i} month");
            $labels[] = $d->format('m/Y');
        }

        // Find year bounds in DB
        $yrRes = $conn->query("SELECT MIN(YEAR(ngaynhap)) AS miny, MAX(YEAR(ngaynhap)) AS maxy FROM chisodien");
        $yrRow = $yrRes ? $yrRes->fetch_assoc() : null;
        $miny = ($yrRow && isset($yrRow['miny'])) ? intval($yrRow['miny']) : intval($start->format('Y'));
        $maxy = ($yrRow && isset($yrRow['maxy'])) ? intval($yrRow['maxy']) : intval($end->format('Y'));
        if ($miny <= 0) $miny = intval($start->format('Y'));
        if ($maxy <= 0) $maxy = intval($end->format('Y'));

        $series = [];
        $trend = [];
        $summary = ['total_kwh' => 0, 'avg_month_kwh' => 0, 'avg_day_kwh' => 0, 'max_month_kwh' => 0, 'min_month_kwh' => 0];

        // If specific maKH provided, use old flat trend for compatibility
        if ($maKH !== '') {
            $current = (clone $start)->modify('first day of this month');
            $lastMonth = (clone $end)->modify('first day of this month');
            $periodMonths = [];
            while ($current <= $lastMonth) {
                $periodMonths[] = $current->format('Y-m');
                $current->modify('+1 month');
            }

            $sql = "SELECT DATE_FORMAT(ngaynhap, '%Y-%m') AS month, COALESCE(SUM(chisomoi - chisocu), 0) AS total_kwh FROM chisodien";
            $clauses = [];
            $params = [];
            if ($maKH !== '') {
                $clauses[] = 'maKH = ?';
                $params[] = $maKH;
            }
            $clauses[] = 'ngaynhap BETWEEN ? AND ?';
            $params[] = $from;
            $params[] = $to;
            if ($clauses) {
                $sql .= ' WHERE ' . implode(' AND ', $clauses);
            }
            $sql .= " GROUP BY month ORDER BY month ASC";

            $monthlyResults = [];
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $monthlyResults[$row['month']] = floatval($row['total_kwh']);
                }
                $stmt->close();
            }

            $totalKwh = 0;
            $monthValues = [];
            foreach ($periodMonths as $month) {
                $value = isset($monthlyResults[$month]) ? $monthlyResults[$month] : 0;
                $dateObj = DateTime::createFromFormat('Y-m', $month);
                $monthFormatted = $dateObj ? $dateObj->format('m/Y') : $month;
                $trend[] = ['month' => $monthFormatted, 'total_kwh' => $value];
                $totalKwh += $value;
                $monthValues[] = $value;
            }

            $summary = [
                'total_kwh' => round($totalKwh, 2),
                'avg_month_kwh' => round($totalKwh / max(1, count($monthValues)), 2),
                'avg_day_kwh' => 0,
                'max_month_kwh' => round(count($monthValues) ? max($monthValues) : 0, 2),
                'min_month_kwh' => round(count($monthValues) ? min($monthValues) : 0, 2)
            ];

            $days = max(1, intval(floor((strtotime($to) - strtotime($from)) / 86400) + 1));
            $summary['avg_day_kwh'] = round($summary['total_kwh'] / $days, 2);

            send_json(['success' => true, 'summary' => $summary, 'trend' => $trend]);
        } else {
            // Multi-year comparison (all customers)
            for ($yr = $miny; $yr <= $maxy; $yr++) {
                $periodStart = DateTime::createFromFormat('Y-n-j', sprintf('%04d-%d-%d', $yr, intval($start->format('n')), 1));
                if (!$periodStart) continue;
                $periodEnd = (clone $periodStart)->modify('+' . ($monthsCount - 1) . ' month')->modify('last day of this month');

                $sql = "SELECT DATE_FORMAT(ngaynhap, '%Y-%m') AS ym, COALESCE(SUM(chisomoi - chisocu), 0) AS total_kwh FROM chisodien WHERE ngaynhap BETWEEN ? AND ? GROUP BY ym ORDER BY ym ASC";
                $stmt = $conn->prepare($sql);
                if (!$stmt) continue;
                $ps = $periodStart->format('Y-m-d 00:00:00');
                $pe = $periodEnd->format('Y-m-d 23:59:59');
                $stmt->bind_param('ss', $ps, $pe);
                $stmt->execute();
                $res = $stmt->get_result();
                $map = [];
                while ($r = $res->fetch_assoc()) {
                    $map[$r['ym']] = floatval($r['total_kwh']);
                }
                $stmt->close();

                $data = [];
                for ($i = 0; $i < $monthsCount; $i++) {
                    $d = (clone $periodStart)->modify("+{$i} month");
                    $ym = $d->format('Y-m');
                    $data[] = isset($map[$ym]) ? $map[$ym] : 0;
                }

                $series[] = ['year' => (string)$yr, 'data' => $data];
            }

            send_json(['success' => true, 'labels' => $labels, 'series' => $series]);
        }
        break;
        break;

    default:
        send_json(['success' => false, 'error' => 'Loại thống kê không hợp lệ.']);
        break;
}

$conn->close();
