<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$maKH = isset($_POST['maKH']) ? trim($_POST['maKH']) : '';
$thang = isset($_POST['thang']) ? intval($_POST['thang']) : 0;
$nam = isset($_POST['nam']) ? intval($_POST['nam']) : 0;

if (!$maKH || !$thang || !$nam) {
  echo json_encode(['success' => false, 'error' => 'Thiếu thông tin tra cứu']);
  exit;
}

// Kiểm tra tính hợp lệ của tháng/năm
if ($thang < 1 || $thang > 12 || $nam < 2000 || $nam > 2100) {
  echo json_encode(['success' => false, 'error' => 'Tháng hoặc năm không hợp lệ']);
  exit;
}

// Lấy thông tin khách hàng
$stmt = $conn->prepare("SELECT hovaten, sodienthoai, email FROM taikhoan WHERE maKH = ? AND trangthai = 'hoatdong'");
$stmt->bind_param("s", $maKH);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
  echo json_encode(['success' => false, 'error' => 'Mã khách hàng không tồn tại hoặc đã bị khóa']);
  exit;
}

// Lấy chỉ số điện và hóa đơn cho tháng/năm
$stmt = $conn->prepare(
  "SELECT c.maCSD, c.chisocu, c.chisomoi, h.maHD, h.sodiendatieuthu, h.tongtien, h.trangthai " .
  "FROM chisodien c " .
  "JOIN hoadon h ON c.maCSD = h.maCSD " .
  "WHERE c.maKH = ? AND c.thang = ? AND c.nam = ? LIMIT 1"
);
$stmt->bind_param("sii", $maKH, $thang, $nam);
$stmt->execute();
$result = $stmt->get_result();
$invoiceData = $result->fetch_assoc();

if (!$invoiceData) {
  echo json_encode(['success' => false, 'error' => "Không tìm thấy hóa đơn cho kỳ $thang/$nam của khách hàng $maKH"]);
  exit;
}

// Tính sản lượng điện tiêu thụ dựa trên chỉ số điện
$soKWh = $invoiceData['chisomoi'] - $invoiceData['chisocu'];

$maCSD = $invoiceData['maCSD'];
$invoice = [
  'maHD' => $invoiceData['maHD'],
  'maCSD' => $maCSD,
  'chisocu' => intval($invoiceData['chisocu']),
  'chisomoi' => intval($invoiceData['chisomoi']),
  'sodiendatieuthu' => $invoiceData['sodiendatieuthu'],
  'tongtien' => $invoiceData['tongtien'],
  'trangthai' => $invoiceData['trangthai'],
  'soKWh' => $soKWh
];

if (empty($invoice)) {
  echo json_encode(['success' => false, 'error' => "Hóa đơn chưa được tạo cho tháng $thang/$nam"]);
  exit;
}

$monthStart = sprintf('%04d-%02d-01', $nam, $thang);
$monthEnd = date('Y-m-t', strtotime($monthStart));

$tariffChange = [
  'changed' => false,
  'effective_date' => null,
  'before_kwh' => 0,
  'after_kwh' => 0,
  'note' => '',
  'old_rates' => [],
  'new_rates' => []
];

$oldTariffRates = [];
$newTariffRates = [];

$stmt = $conn->prepare(
  "SELECT DISTINCT ngayapdung FROM giadien WHERE ngayapdung > ? AND ngayapdung <= ? ORDER BY ngayapdung ASC"
);
$stmt->bind_param('ss', $monthStart, $monthEnd);
$stmt->execute();
$result = $stmt->get_result();
$changeDate = null;
if ($row = $result->fetch_assoc()) {
  $changeDate = $row['ngayapdung'];
}
$stmt->close();

if ($changeDate) {
  $changeDateStr = date('Y-m-d', strtotime($changeDate));
  $tariffChange['changed'] = true;
  $tariffChange['effective_date'] = $changeDateStr;
  $tariffChange['note'] = "Giá mới được áp dụng từ ngày " . date('d/m/Y', strtotime($changeDateStr)) . " trong kỳ này.";

  $stmt = $conn->prepare(
    "SELECT bac, dongia FROM giadien WHERE ngayapdung < ? ORDER BY bac ASC, ngayapdung DESC"
  );
  $stmt->bind_param('s', $changeDateStr);
  $stmt->execute();
  $result = $stmt->get_result();
  $seenOldBac = [];
  while ($row = $result->fetch_assoc()) {
    $bac = intval($row['bac']);
    if (isset($seenOldBac[$bac])) {
      continue;
    }
    $seenOldBac[$bac] = true;
    $oldTariffRates[] = [
      'bac' => $bac,
      'dongia' => floatval($row['dongia'])
    ];
  }
  $stmt->close();

  $stmt = $conn->prepare(
    "SELECT bac, dongia FROM giadien WHERE ngayapdung <= ? ORDER BY bac ASC, ngayapdung DESC"
  );
  $stmt->bind_param('s', $changeDateStr);
  $stmt->execute();
  $result = $stmt->get_result();
  $seenNewBac = [];
  while ($row = $result->fetch_assoc()) {
    $bac = intval($row['bac']);
    if (isset($seenNewBac[$bac])) {
      continue;
    }
    $seenNewBac[$bac] = true;
    $newTariffRates[] = [
      'bac' => $bac,
      'dongia' => floatval($row['dongia'])
    ];
  }
  $stmt->close();

  $tariffChange['old_rates'] = $oldTariffRates;
  $tariffChange['new_rates'] = $newTariffRates;

  $changeDay = intval(date('j', strtotime($changeDateStr)));
  $totalDays = intval(date('t', strtotime($monthStart)));
  $beforeDays = max(0, $changeDay - 1);
  $beforeKwh = $totalDays > 0 ? round($soKWh * $beforeDays / $totalDays) : 0;
  $afterKwh = max(0, $soKWh - $beforeKwh);
  $tariffChange['before_kwh'] = $beforeKwh;
  $tariffChange['after_kwh'] = $afterKwh;
}

$stmt = $conn->prepare(
  "SELECT bacthang AS bac, dongia, sanluong AS kwh, thanhtien AS amount " .
  "FROM thongketiendien " .
  "WHERE maCSD = ? AND maKH = ? " .
  "ORDER BY bacthang ASC"
);
$stmt->bind_param("is", $maCSD, $maKH);
$stmt->execute();
$result = $stmt->get_result();

$breakdown = [];
$subtotal = 0;
while ($row = $result->fetch_assoc()) {
  $breakdown[] = [
    'bac' => intval($row['bac']),
    'gia' => floatval($row['dongia']),
    'kwh' => intval($row['kwh']),
    'amount' => floatval($row['amount'])
  ];
  $subtotal += floatval($row['amount']);
}

if (empty($breakdown)) {
  echo json_encode(['success' => false, 'error' => 'Không tìm thấy chi tiết thống kê tiền điện.']);
  exit;
}

if ($tariffChange['changed']) {
  $beforeKwh = 0;
  $afterKwh = 0;
  $oldRates = array_column($tariffChange['old_rates'], 'dongia');
  $newRates = array_column($tariffChange['new_rates'], 'dongia');

  foreach ($breakdown as $item) {
    if (in_array($item['gia'], $oldRates, true) && !in_array($item['gia'], $newRates, true)) {
      $beforeKwh += $item['kwh'];
    } elseif (in_array($item['gia'], $newRates, true) && !in_array($item['gia'], $oldRates, true)) {
      $afterKwh += $item['kwh'];
    } else {
      // If the same price appears in both tariff groups, we cannot reliably distinguish the exact split by rate value alone.
      // In that case keep the total usage as the full monthly consumption and the invoice still shows the change date.
    }
  }

  if ($beforeKwh === 0 && $afterKwh === 0) {
    $changeDay = intval(date('j', strtotime($tariffChange['effective_date'])));
    $totalDays = intval(date('t', strtotime($monthStart)));
    $daysBefore = max(0, $changeDay - 1);
    $daysAfter = max(0, $totalDays - $daysBefore);
    $beforeKwh = $totalDays > 0 ? round($soKWh * $daysBefore / $totalDays) : 0;
    $afterKwh = max(0, $soKWh - $beforeKwh);
  }

  $tariffChange['before_kwh'] = $beforeKwh;
  $tariffChange['after_kwh'] = $afterKwh;
}

$tax = round($subtotal * 0.08);
$total = round($subtotal + $tax);

$stmt = $conn->prepare("SELECT maTT, sotien FROM thanhtoan WHERE maHD = ? AND trangthai = 'chuathanhtoan' LIMIT 1");
$stmt->bind_param("i", $invoiceData['maHD']);
$stmt->execute();
$result = $stmt->get_result();
$paymentRow = $result->fetch_assoc();
$stmt->close();

$pendingPayment = null;
if ($paymentRow) {
  $pendingPayment = [
    'maTT' => intval($paymentRow['maTT']),
    'sotien' => floatval($paymentRow['sotien'])
  ];
}

echo json_encode([
  'success' => true,
  'customer' => [
    'maKH' => $maKH,
    'hovaten' => $customer['hovaten'],
    'sodienthoai' => $customer['sodienthoai'],
    'email' => $customer['email']
  ],
  'invoice' => [
    'maHD' => $invoice['maHD'],
    'maCSD' => $invoice['maCSD'],
    'chisocu' => $invoice['chisocu'],
    'chisomoi' => $invoice['chisomoi'],
    'soKWh' => $soKWh,
    'trangthai' => $invoice['trangthai']
  ],
  'period' => [
    'thang' => $thang,
    'nam' => $nam
  ],
  'breakdown' => $breakdown,
  'subtotal' => $subtotal,
  'tax' => $tax,
  'total' => $total,
  'payment' => $pendingPayment,
  'tariff_change' => $tariffChange
]);

$conn->close();
?>
