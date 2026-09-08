<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.html');
    exit;
}

$maKH = $_SESSION['user']['maKH'];

$sql = "
    SELECT c.nam, c.thang, c.chisocu, c.chisomoi,
           (c.chisomoi - c.chisocu) AS kwh,
           h.tongtien, h.trangthai
    FROM chisodien c
    LEFT JOIN hoadon h ON h.maCSD = c.maCSD
    WHERE c.maKH = ?
    ORDER BY c.nam ASC, c.thang ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $maKH);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = [
        'year' => intval($row['nam']),
        'month' => intval($row['thang']),
        'monthLabel' => 'Tháng ' . intval($row['thang']) . '/' . intval($row['nam']),
        'kwh' => floatval($row['kwh']),
        'amount' => floatval($row['tongtien'] ?? 0),
        'status' => $row['trangthai'] ?? 'chuathanhtoan'
    ];
}
$stmt->close();

$years = [];
foreach ($records as $record) {
    $years[$record['year']] = $record['year'];
}
$selectedYear = !empty($years) ? max(array_keys($years)) : date('Y');
$yearlyData = [];
foreach ($records as $record) {
    $yearlyData[$record['year']][] = $record;
}

$yearSummary = [];
foreach ($years as $year) {
    $items = $yearlyData[$year] ?? [];
    $totalKwh = array_sum(array_map(fn($item) => $item['kwh'], $items));
    $totalAmount = array_sum(array_map(fn($item) => $item['amount'], $items));
    $avgKwh = $items ? $totalKwh / count($items) : 0;

    $maxMonth = null;
    $minMonth = null;
    foreach ($items as $item) {
        if ($maxMonth === null || $item['kwh'] > $maxMonth['kwh']) {
            $maxMonth = $item;
        }
        if ($minMonth === null || $item['kwh'] < $minMonth['kwh']) {
            $minMonth = $item;
        }
    }

    $yearSummary[$year] = [
        'totalKwh' => $totalKwh,
        'totalAmount' => $totalAmount,
        'avgKwh' => $avgKwh,
        'maxMonth' => $maxMonth,
        'minMonth' => $minMonth,
    ];
}

$defaultSummary = $yearSummary[$selectedYear] ?? [
    'totalKwh' => 0,
    'totalAmount' => 0,
    'avgKwh' => 0,
    'maxMonth' => null,
    'minMonth' => null,
];

$defaultSummaryJson = json_encode($defaultSummary);
$recordsJson = json_encode($records);
$yearsJson = json_encode(array_values($years));
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Biểu đồ sử dụng điện</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="assets/js/chart.umd.min.js"></script>
  <style>
    body { background: #f3f7ff; }
    .usage-page { padding: 28px 0 56px; }
    .usage-topbar {
      display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
      margin-bottom:20px;
    }
    .usage-topbar h1 {
      margin:0; color:#1f355b; font-size:28px; font-weight:800;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      letter-spacing: 0.2px;
    }
    .year-selector {
      display:flex; align-items:center; gap:12px; background:#fff; border:1px solid #dfeaf8;
      border-radius:14px; padding:8px 12px; box-shadow:0 8px 22px rgba(23,63,139,.06);
    }
    .year-selector label { color:#495d83; font-weight:700; }
    .year-selector select {
      border:1px solid #dfeaf8; border-radius:10px; padding:10px 12px; font-size:15px; color:#173f8b;
      background:#f9fbff; min-width:120px;
    }
    .stat-grid {
      display:grid; grid-template-columns:repeat(4, minmax(180px, 1fr)); gap:18px; margin-bottom:20px;
    }
    .stat-box {
      background:#fff; border:1px solid #dfeaf8; border-radius:18px; padding:18px 20px; box-shadow:0 12px 26px rgba(20,40,80,.04);
    }
    .stat-box h3 { margin:0 0 12px; font-size:14px; color:#4a5d7c; }
    .stat-box strong { font-size:28px; color:#173f8b; display:block; }
    .stat-box small { color:#64748b; }
    .chart-grid {
      display:grid; grid-template-columns: 1.5fr 1fr; gap:20px;
    }
    .panel {
      background:#fff; border:1px solid #dfeaf8; border-radius:18px; padding:22px 22px 16px; box-shadow:0 14px 30px rgba(23,63,139,.05);
      min-height: 420px;
      display:flex;
      flex-direction:column;
    }
    .panel-header {
      display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px;
    }
    .panel h3 {
      margin:0; color:#1f355b; font-size:18px; font-weight:800;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      letter-spacing: 0.15px;
    }
    .panel-subtitle {
      margin:6px 0 0; font-size:13px; color:#5d728d;
    }
    .chart-shell {
      border:1px solid #e4edf7; border-radius:18px; background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      padding:16px 18px 14px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
    }
    #moneyChart {
      width: 100% !important;
      height: 360px !important;
      display: block;
      min-height: 300px;
      font-family: 'Segoe UI', Tahoma, sans-serif;
    }
    .year-switcher {
      display:flex; justify-content:center; gap:10px; flex-wrap:wrap; margin-top:18px;
    }
    .year-pill {
      border:none; background:#1f4c9d; color:#fff; font-weight:700; border-radius:999px;
      padding:10px 18px; min-width:80px; cursor:pointer; box-shadow:0 8px 18px rgba(31,76,157,.18);
      transition:all .2s ease;
    }
    .year-pill.inactive {
      background:#dfeaf8; color:#173f8b; box-shadow:none;
    }
    .year-pill:hover { transform:translateY(-1px); }
    .insight-list {
      list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:12px;
    }
    .insight-list li {
      display:flex; gap:12px; align-items:flex-start; background:#f7faff; border:1px solid #e1ebf7; border-radius:12px; padding:12px 14px;
      color:#33517e; line-height:1.6;
    }
    .insight-list .badge {
      display:inline-flex; align-items:center; justify-content:center; min-width:28px; height:28px; border-radius:50%;
      background:#1c4ab2; color:#fff; font-weight:700; font-size:13px; flex-shrink:0;
    }
    .analysis-grid {
      display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:18px; margin-top:24px;
    }
    .mini-card {
      background:#f8fbff; border:1px solid #dfeaf8; border-radius:16px; padding:18px;
    }
    .mini-card h4 {
      margin:0 0 10px; color:#173f8b;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-weight:700;
    }
    .mini-card p { margin:0; color:#4d607d; line-height:1.6; }
    @media (max-width: 920px) {
      .chart-grid, .stat-grid, .analysis-grid { grid-template-columns: 1fr; }
      .usage-topbar { align-items:flex-start; }
      #moneyChart { height: 320px !important; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-top">
      <div class="container header-top-inner">
        <div class="brand">
          <a class="brand-left" href="customer.php">
            <img src="assets/img/Logo-EVN-V-1.webp" alt="EVN" />
            <div class="brand-text">
              <div class="brand-title">TẬP ĐOÀN ĐIỆN LỰC VIỆT NAM</div>
              <div class="brand-sub">TRANG THÔNG TIN ĐIỆN TỬ TỔNG HỢP</div>
            </div>
          </a>
        </div>
        <div class="header-top-right">
          <div class="search">
            <input type="search" placeholder="Tìm kiếm" aria-label="Tìm kiếm">
          </div>
          <div style="margin-left:12px;">
            <a class="btn btn-secondary" href="logout.php">Đăng xuất</a>
          </div>
        </div>
      </div>
    </div>
    <div class="container customer-nav">
      <a href="customer.php" class="customer-nav-item">Trang chủ</a>
      <a href="invoice-search.html" class="customer-nav-item">Tra cứu hóa đơn</a>
      <a href="customer-usage-chart.php" class="customer-nav-item active">Biểu đồ sử dụng điện</a>
      <a href="payment-history.html" class="customer-nav-item">Lịch sử thanh toán</a>
    </div>
  </header>

  <main class="container usage-page">
    <div class="usage-topbar">
      <h1>Biểu đồ sử dụng điện</h1>
      <div class="year-selector">
        <label for="yearSelect">Năm</label>
        <select id="yearSelect" aria-label="Chọn năm">
          <?php foreach (array_values($years) as $year): ?>
            <option value="<?= intval($year) ?>" <?= intval($year) === intval($selectedYear) ? 'selected' : '' ?>><?= intval($year) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <section class="stat-grid">
      <div class="stat-box">
        <h3>Tổng điện tiêu thụ</h3>
        <strong id="totalKwh">0 kWh</strong>
        <small id="avgMonthText">0 kWh/tháng</small>
      </div>
      <div class="stat-box">
        <h3>Tổng tiền điện</h3>
        <strong id="totalMoney">0 ₫</strong>
        <small id="moneyMonthText">0 ₫/tháng</small>
      </div>
      <div class="stat-box">
        <h3>Tháng cao nhất</h3>
        <strong id="peakMonth">-</strong>
        <small id="peakAmount">-</small>
      </div>
      <div class="stat-box">
        <h3>Xu hướng</h3>
        <strong id="trendText">-</strong>
        <small id="trendDetail">-</small>
      </div>
    </section>

    <section class="chart-grid">
      <div class="panel">
        <div class="panel-header">
          <div>
            <h3>Biểu đồ sử dụng điện</h3>
            <p class="panel-subtitle">So sánh tiêu thụ kWh và số tiền theo từng tháng trong năm.</p>
          </div>
        </div>
        <div class="chart-shell">
          <canvas id="moneyChart"></canvas>
        </div>
        <div class="year-switcher" id="yearSwitcher"></div>
      </div>
      <div class="panel">
        <h3>Phân tích sử dụng điện</h3>
        <ul class="insight-list" id="insightList">
          <li><span class="badge">1</span><span>Đang tính toán mức tiêu thụ và xu hướng...</span></li>
        </ul>
      </div>
    </section>

    <section class="analysis-grid">
      <div class="mini-card">
        <h4>Điểm nổi bật</h4>
        <p id="highlightText">Chưa có dữ liệu đủ để đánh giá.</p>
      </div>
      <div class="mini-card">
        <h4>Khuyến nghị</h4>
        <p id="recommendationText">Vui lòng chọn năm để xem phân tích chi tiết.</p>
      </div>
    </section>
  </main>

  <footer class="site-footer footer-simple">
    <div class="container">
      <p>© 2026 Trọng Thành - Trang quản lý tiền điện.</p>
    </div>
  </footer>

  <script>
    const usageData = <?php echo $recordsJson; ?>;
    const yearOptions = <?php echo $yearsJson; ?>;
    const defaultSummary = <?php echo $defaultSummaryJson; ?>;

    const yearSelect = document.getElementById('yearSelect');
    const yearSwitcher = document.getElementById('yearSwitcher');
    const moneyChartEl = document.getElementById('moneyChart');
    const totalKwhEl = document.getElementById('totalKwh');
    const avgMonthTextEl = document.getElementById('avgMonthText');
    const totalMoneyEl = document.getElementById('totalMoney');
    const moneyMonthTextEl = document.getElementById('moneyMonthText');
    const peakMonthEl = document.getElementById('peakMonth');
    const peakAmountEl = document.getElementById('peakAmount');
    const trendTextEl = document.getElementById('trendText');
    const trendDetailEl = document.getElementById('trendDetail');
    const insightListEl = document.getElementById('insightList');
    const highlightTextEl = document.getElementById('highlightText');
    const recommendationTextEl = document.getElementById('recommendationText');

    const palette = {
      2023: '#2f5eea',
      2024: '#1eb7aa',
      2025: '#f59f0b',
      2026: '#ef6c3d'
    };

    function formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(value) + ' ₫';
    }

    function formatKwh(value) {
      return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(value) + ' kWh';
    }

    function buildYearMap() {
      const map = {};
      usageData.forEach(item => {
        if (!map[item.year]) map[item.year] = [];
        map[item.year].push(item);
      });
      return map;
    }

    function getSelectedYearData(year) {
      const map = buildYearMap();
      return (map[year] || []).sort((a, b) => a.month - b.month);
    }

    function getSummary(items) {
      if (!items.length) {
        return { totalKwh: 0, totalMoney: 0, avgKwh: 0, peakMonth: '-', peakAmount: 0, trend: 'Không có dữ liệu' };
      }

      const totalKwh = items.reduce((sum, item) => sum + Number(item.kwh || 0), 0);
      const totalMoney = items.reduce((sum, item) => sum + Number(item.amount || 0), 0);
      const avgKwh = totalKwh / items.length;

      const peak = items.reduce((best, item) => {
        if (!best || Number(item.kwh || 0) > Number(best.kwh || 0)) return item;
        return best;
      }, null);

      const monthGap = items.length > 1 ? items[items.length - 1].kwh - items[0].kwh : 0;
      const trend = monthGap > 0 ? 'Tăng' : (monthGap < 0 ? 'Giảm' : 'Ổn định');

      return {
        totalKwh,
        totalMoney,
        avgKwh,
        peakMonth: peak ? `Tháng ${peak.month}/${peak.year}` : '-',
        peakAmount: peak ? Number(peak.kwh || 0) : 0,
        trend,
        trendValue: monthGap
      };
    }

    function renderSummary(year) {
      const items = getSelectedYearData(year);
      const summary = getSummary(items);

      totalKwhEl.textContent = formatKwh(summary.totalKwh);
      avgMonthTextEl.textContent = `${formatKwh(summary.avgKwh)} / tháng`;
      totalMoneyEl.textContent = formatCurrency(summary.totalMoney);
      moneyMonthTextEl.textContent = `${formatCurrency(summary.totalMoney / Math.max(items.length, 1))} / tháng`;
      peakMonthEl.textContent = summary.peakMonth;
      peakAmountEl.textContent = summary.peakAmount ? `${formatKwh(summary.peakAmount)} tiêu thụ` : '-';
      trendTextEl.textContent = summary.trend;
      trendDetailEl.textContent = summary.peakMonth !== '-' ? `Khác biệt ${Math.abs(summary.trendValue).toFixed(1)} kWh giữa đầu và cuối năm` : 'Chưa có dữ liệu';

      const avg = summary.avgKwh || 0;
      const highest = items.length ? Math.max(...items.map(i => Number(i.kwh || 0))) : 0;
      const lowest = items.length ? Math.min(...items.map(i => Number(i.kwh || 0))) : 0;

      const insights = [
        `Tổng điện tiêu thụ năm ${year}: <strong>${formatKwh(summary.totalKwh)}</strong>.`,
        `Trung bình mỗi tháng: <strong>${formatKwh(summary.avgKwh)}</strong>.`,
        `Tháng cao nhất: <strong>${summary.peakMonth}</strong> với <strong>${formatKwh(summary.peakAmount)}</strong>.`,
        `Mức tiêu thụ thấp nhất trong năm là <strong>${formatKwh(lowest)}</strong>, cao nhất là <strong>${formatKwh(highest)}</strong>.`
      ];

      insightListEl.innerHTML = insights.map((text, index) => `
        <li><span class="badge">${index + 1}</span><span>${text}</span></li>
      `).join('');

      const recommendation = summary.trend === 'Tăng'
        ? 'Tiêu thụ đang có xu hướng tăng, nên kiểm tra các thiết bị tiêu thụ nhiều điện và ưu tiên tiết kiệm trong mùa cao điểm.'
        : summary.trend === 'Giảm'
          ? 'Tiêu thụ đang có xu hướng giảm, đây là tín hiệu khả quan. Tiếp tục duy trì thói quen sử dụng điện tiết kiệm.'
          : 'Mức tiêu thụ tương đối ổn định, bạn có thể theo dõi gần hơn nếu tiêu thụ muốn duy trì ở mức hợp lý.';

      const highlight = items.length
        ? `Năm ${year} có tổng chi phí ${formatCurrency(summary.totalMoney)} với mức tiêu thụ trung bình ${formatKwh(summary.avgKwh)} mỗi tháng.`
        : 'Chưa có dữ liệu thống kê cho năm này.';

      highlightTextEl.innerHTML = highlight;
      recommendationTextEl.textContent = recommendation;
    }

    function renderYearButtons() {
      const years = [...new Set(yearOptions)].sort((a, b) => Number(a) - Number(b));
      yearSwitcher.innerHTML = years.map(year => `
        <button class="year-pill ${Number(yearSelect.value) === Number(year) ? '' : 'inactive'}" data-year="${year}">${year}</button>
      `).join('');

      yearSwitcher.querySelectorAll('.year-pill').forEach(button => {
        button.addEventListener('click', () => {
          const selectedYear = Number(button.dataset.year);
          yearSelect.value = selectedYear;
          updateYear(selectedYear);
        });
      });
    }

    function renderChart(year) {
      const items = getSelectedYearData(year);
      const monthLabels = Array.from({ length: 12 }, (_, index) => `Tháng ${index + 1}`);
      const kwhData = monthLabels.map((_, index) => {
        const month = index + 1;
        const entry = items.find(item => Number(item.month) === month);
        return entry ? Number(entry.kwh || 0) : 0;
      });
      const moneyData = monthLabels.map((_, index) => {
        const month = index + 1;
        const entry = items.find(item => Number(item.month) === month);
        return entry ? Number(entry.amount || 0) : 0;
      });

      if (window.usageChartInstance) {
        window.usageChartInstance.destroy();
      }

      window.usageChartInstance = new Chart(moneyChartEl, {
        type: 'line',
        data: {
          labels: monthLabels,
          datasets: [
            {
              label: 'kWh',
              data: kwhData,
              yAxisID: 'yKwh',
              borderColor: '#1f6fe5',
              backgroundColor: 'rgba(31, 111, 229, 0.12)',
              borderWidth: 3,
              pointRadius: 4,
              pointBackgroundColor: '#1f6fe5',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              tension: 0.35,
              fill: false
            },
            {
              label: 'Số tiền',
              data: moneyData,
              yAxisID: 'yMoney',
              borderColor: '#f28a39',
              backgroundColor: 'rgba(242, 138, 57, 0.12)',
              borderWidth: 3,
              pointRadius: 4,
              pointBackgroundColor: '#f28a39',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              tension: 0.35,
              fill: false
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          animation: { duration: 420 },
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                color: '#294669',
                padding: 16,
                boxWidth: 12,
                font: {
                  family: 'Segoe UI, Tahoma, sans-serif',
                  size: 12,
                  weight: '600'
                }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(23, 63, 139, 0.95)',
              titleColor: '#ffffff',
              bodyColor: '#ffffff',
              borderColor: 'rgba(255,255,255,0.18)',
              borderWidth: 1,
              padding: 10,
              displayColors: true,
              callbacks: {
                title: items => items.length ? items[0].label : '',
                label: ctx => ctx.dataset.label === 'kWh'
                  ? `${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString('vi-VN')} kWh`
                  : `${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString('vi-VN')} ₫`
              }
            }
          },
          scales: {
            x: {
              grid: { color: 'rgba(148,163,184,0.18)', drawBorder: false },
              ticks: {
                color: '#52657d',
                font: {
                  family: 'Segoe UI, Tahoma, sans-serif',
                  size: 11,
                  weight: '600'
                },
                autoSkip: false
              }
            },
            yKwh: {
              type: 'linear',
              position: 'left',
              beginAtZero: false,
              title: {
                display: true,
                text: 'kWh',
                color: '#1f6fe5',
                font: {
                  family: 'Segoe UI, Tahoma, sans-serif',
                  size: 12,
                  weight: '700'
                }
              },
              grid: { color: 'rgba(148,163,184,0.18)', drawBorder: false },
              ticks: {
                color: '#1f6fe5',
                font: {
                  family: 'Segoe UI, Tahoma, sans-serif',
                  size: 11,
                  weight: '600'
                },
                callback: value => `${Number(value).toLocaleString('vi-VN')}`
              }
            },
            yMoney: {
              type: 'linear',
              position: 'right',
              beginAtZero: false,
              title: {
                display: true,
                text: 'Số tiền (₫)',
                color: '#f28a39',
                font: {
                  family: 'Segoe UI, Tahoma, sans-serif',
                  size: 12,
                  weight: '700'
                }
              },
              grid: { drawOnChartArea: false },
              ticks: {
                color: '#f28a39',
                font: {
                  family: 'Segoe UI, Tahoma, sans-serif',
                  size: 11,
                  weight: '600'
                },
                callback: value => `${Number(value).toLocaleString('vi-VN')}`
              }
            }
          }
        }
      });
    }

    function updateYear(year) {
      renderSummary(year);
      renderChart(year);
      renderYearButtons();
    }

    yearSelect.addEventListener('change', function () {
      updateYear(Number(this.value));
    });

    document.addEventListener('DOMContentLoaded', function () {
      const initialYear = yearSelect.value ? Number(yearSelect.value) : new Date().getFullYear();
      updateYear(initialYear);
    });
  </script>
  <script src="assets/js/i18n.js"></script>
</body>
</html>
